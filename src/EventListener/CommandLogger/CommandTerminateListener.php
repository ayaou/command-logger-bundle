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

namespace Ayaou\CommandLoggerBundle\EventListener\CommandLogger;

use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

/**
 * @internal
 */
class CommandTerminateListener extends AbstractCommandListener
{
    private CommandLogWriter $writer;

    private CommandExecutionTracker $commandExecutionTracker;

    private bool $enabled;

    /**
     * @var array<int|string, string>
     */
    private array $otherCommands;

    /**
     * @var array<int, string>
     */
    private array $attributedCommands;

    private ?LoggerInterface $logger;

    /**
     * @param array<int|string, string> $otherCommands
     * @param array<int, string>        $attributedCommands names (and aliases) collected at
     *                                                      compile time by CommandLoggerPass
     */
    public function __construct(
        CommandLogWriter $writer,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
        array $otherCommands = [],
        array $attributedCommands = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->writer = $writer;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled = $enabled;
        $this->otherCommands = $otherCommands;
        $this->attributedCommands = $attributedCommands;
        $this->logger = $logger;
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->enabled || !$command || !$this->isSupportedCommand($command, $this->otherCommands, $this->attributedCommands)) {
            return;
        }

        $executionToken = $this->commandExecutionTracker->getToken($command);
        if (!$executionToken) {
            return;
        }

        // Read before clearToken() below wipes it. Left null - rather than a computed guess
        // - when no start instant was recorded (e.g. the tracker was cleared in between).
        $startTimestamp = $this->commandExecutionTracker->getStartTimestamp($command);
        $durationMs = null !== $startTimestamp ? intdiv(hrtime(true) - $startTimestamp, 1_000_000) : null;

        try {
            // A single UPDATE keyed on executionToken: no SELECT is issued first. A token
            // with no matching row (e.g. the start write itself previously failed) simply
            // updates zero rows - there is nothing more to do about it here.
            $this->writer->markTerminated($executionToken, new \DateTimeImmutable(), $event->getExitCode(), $durationMs);
        } catch (\Throwable $exception) {
            // A logging failure must never take the user's command down with it: give up on
            // writing this entry and let the command run its course.
            $this->logger?->error(
                sprintf('Command logger bundle failed to log the termination of command "%s".', $command->getName()),
                ['command' => $command->getName(), 'exception' => $exception],
            );
        }

        $this->commandExecutionTracker->clearToken($command);
    }
}
