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

/**
 * The PHP stream filter that observes what a watched command writes.
 *
 * Symfony offers no way to substitute the OutputInterface a command receives:
 * ConsoleEvent holds it in a private property with no setter, so decorating it from a
 * console.command listener is impossible. StreamOutput::getStream() is public, though,
 * so the bytes can be observed one level lower - on the stream resource itself.
 *
 * Two properties of this class are load-bearing and must survive any refactoring:
 *
 * 1. The bucket is passed on BEFORE anything else happens. Whatever the state of the
 *    capture, the command's own output reaches its terminal untouched.
 * 2. Nothing here throws. An exception raised inside a stream filter does not degrade
 *    into a warning: it propagates out of the fwrite() the command was performing, and
 *    it is raised again when the stream is closed. A throwing filter therefore kills the
 *    command being observed. Capture is abandoned on the first sign of trouble instead.
 *
 * The sink is static because a stream filter is instantiated by the PHP engine, which
 * offers no way to pass constructor arguments. OutputCapture owns that static: it is the
 * only class that writes to it, and it clears it when the command terminates.
 *
 * @internal
 */
class OutputCaptureFilter extends \php_user_filter
{
    public const FILTER_NAME = 'ayaou.command_logger.output_capture';

    /**
     * The buffer every attached filter instance feeds. Null means "capture is off", which
     * is also the state this filter falls back to if appending ever misbehaves.
     */
    public static ?OutputBuffer $sink = null;

    /**
     * @param resource $in
     * @param resource $out
     * @param int      $consumed
     */
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $data = $bucket->data;
            $consumed += $bucket->datalen;

            // Pass-through first, unconditionally: the observed command's output is never
            // hostage to anything this bundle does afterwards.
            stream_bucket_append($out, $bucket);

            try {
                self::$sink?->append($data);
            } catch (\Throwable) {
                // Give up on capturing, never on the command. Dropping the sink also makes
                // every later call a single null check.
                self::$sink = null;
            }
        }

        return PSFS_PASS_ON;
    }
}
