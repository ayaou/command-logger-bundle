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

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

class CommandTerminateListener extends AbstractCommandListener
{
    private EntityManagerInterface $entityManager;

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
        EntityManagerInterface $entityManager,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
        array $otherCommands = [],
        array $attributedCommands = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->entityManager = $entityManager;
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
            $log = $this->entityManager->getRepository(CommandLog::class)
                ->findOneBy(['executionToken' => $executionToken]);

            if ($log) {
                $log->setEndTime(new \DateTimeImmutable())
                    ->setExitCode($event->getExitCode())
                    ->setDurationMs($durationMs);

                $this->entityManager->persist($log);
                $this->entityManager->flush();
            }
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
