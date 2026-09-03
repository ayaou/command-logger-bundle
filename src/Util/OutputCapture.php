<?php

declare(strict_types=1);

/*
 * This file is part of the command logger bundle.
 *
 * (c) Mohamed AYAOU <github.com/ayaou>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ayaou\CommandLoggerBundle\Util;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Attaches and detaches the output capture around one command execution.
 *
 * Disabled by default: capturing what a command prints means storing whatever it printed,
 * which may include secrets no argument-name-based redaction can recognise. Turning it on
 * is therefore an explicit decision - see "command_logger.capture_output" in README.md.
 *
 * When it is off, no filter is registered and no stream is touched, so the cost to a
 * watched command is exactly zero. When it is on, the cost is one bounded string append
 * per write (measured at ~0.5 microseconds per line) and a memory footprint capped by
 * max_output_length, never proportional to how much the command prints.
 *
 * Neither start() nor stop() throws: they run outside the try/catch the listeners wrap
 * their database writes in, so an exception escaping here would take down the very
 * command this bundle is only supposed to observe.
 *
 * @internal
 */
class OutputCapture
{
    private const TRUNCATION_SUFFIX = ' [truncated]';

    private static bool $filterRegistered = false;

    private bool $enabled;

    private int $maxOutputLength;

    /**
     * Handles returned by stream_filter_append(), kept so stop() can detach exactly what
     * start() attached.
     *
     * @var array<int, resource>
     */
    private array $handles = [];

    private ?OutputBuffer $buffer = null;

    public function __construct(bool $enabled = false, int $maxOutputLength = 16384)
    {
        $this->enabled = $enabled;
        $this->maxOutputLength = $maxOutputLength;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Begins capturing what $output writes. A no-op when capture is disabled, when a
     * capture is already running (a command delegating to another one must not restart
     * it), or when the output is not backed by a stream this bundle can observe -
     * NullOutput and BufferedOutput are simply left alone.
     */
    public function start(OutputInterface $output): void
    {
        if (!$this->enabled || null !== $this->buffer) {
            return;
        }

        try {
            if (!self::$filterRegistered) {
                stream_filter_register(OutputCaptureFilter::FILTER_NAME, OutputCaptureFilter::class);
                self::$filterRegistered = true;
            }

            $buffer = new OutputBuffer($this->maxOutputLength);
            $seen = [];

            foreach ($this->collectStreams($output) as $stream) {
                // stdout and stderr are distinct resources, but a caller is free to build a
                // ConsoleOutput over the same one twice - attaching two filters to it would
                // record every byte twice.
                $id = (int) $stream;
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                $handle = @stream_filter_append($stream, OutputCaptureFilter::FILTER_NAME, \STREAM_FILTER_WRITE);
                if (\is_resource($handle)) {
                    $this->handles[] = $handle;
                }
            }

            if ([] === $this->handles) {
                return;
            }

            $this->buffer = $buffer;
            OutputCaptureFilter::$sink = $buffer;
        } catch (\Throwable) {
            // Capture is a best-effort convenience; the command is not.
            $this->reset();
        }
    }

    /**
     * Detaches the filters and returns what was captured, cleaned up for storage. Returns
     * null when capture was off, produced nothing, or failed - the column then stays null
     * rather than holding a misleading empty string.
     */
    public function stop(): ?string
    {
        if (null === $this->buffer) {
            $this->reset();

            return null;
        }

        try {
            $buffer = $this->buffer;

            $this->detach();

            if ($buffer->isEmpty()) {
                return null;
            }

            $contents = $this->clean($buffer->getContents());

            if ('' === $contents) {
                return null;
            }

            return $buffer->isTruncated() ? $contents.self::TRUNCATION_SUFFIX : $contents;
        } catch (\Throwable) {
            return null;
        } finally {
            $this->reset();
        }
    }

    /**
     * @return array<int, resource>
     */
    private function collectStreams(OutputInterface $output): array
    {
        $streams = [];

        if ($output instanceof StreamOutput) {
            $streams[] = $output->getStream();
        }

        if ($output instanceof ConsoleOutputInterface) {
            $errorOutput = $output->getErrorOutput();
            if ($errorOutput instanceof StreamOutput) {
                $streams[] = $errorOutput->getStream();
            }
        }

        return array_values(array_filter($streams, 'is_resource'));
    }

    /**
     * Strips the escape sequences a decorated output emits, then repairs whatever the byte
     * level truncation may have broken.
     *
     * Stripping is not cosmetic. These bytes are replayed later by command-logger:show and
     * by the REST API, and a stored escape sequence is a stored instruction for whichever
     * terminal ends up rendering it. Colours are dropped rather than trusted.
     */
    private function clean(string $contents): string
    {
        // OSC sequences (window title, terminal progress reporting), terminated by BEL or ST.
        $contents = preg_replace('/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)/', '', $contents) ?? $contents;
        // CSI sequences: colours, cursor moves, line erases.
        $contents = preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', $contents) ?? $contents;
        // Remaining two-byte escapes, e.g. charset selection.
        $contents = preg_replace('/\x1b[@-Z\\\\-_]/', '', $contents) ?? $contents;
        // Progress bars redraw the same line: keep only what survived the last carriage return.
        $contents = preg_replace('/[^\n]*\r/', '', $contents) ?? $contents;

        // The buffer cuts on a byte boundary, which can split a multi-byte character in half.
        $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-8');

        return trim($contents, "\0");
    }

    private function detach(): void
    {
        foreach ($this->handles as $handle) {
            if (\is_resource($handle)) {
                @stream_filter_remove($handle);
            }
        }

        $this->handles = [];
    }

    private function reset(): void
    {
        $this->detach();

        $this->buffer = null;
        OutputCaptureFilter::$sink = null;
    }
}
