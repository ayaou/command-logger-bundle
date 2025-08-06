<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

class CommandTerminateListener extends AbstractCommandListener
{
    private EntityManagerInterface $entityManager;

    private CommandExecutionTracker $commandExecutionTracker;

    private bool $enabled;

    private array $otherCommands;

    public function __construct(
        EntityManagerInterface $entityManager,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
        array $otherCommands = [],
    ) {
        $this->entityManager           = $entityManager;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled                 = $enabled;
        $this->otherCommands           = $otherCommands;
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->enabled || !$command || !$this->isSupportedCommand($command, $this->otherCommands)) {
            return;
        }

        $executionToken = $this->commandExecutionTracker->getToken($command);
        if (!$executionToken) {
            return;
        }

        try {
            $log = $this->entityManager->getRepository(CommandLog::class)
                ->findOneBy(['executionToken' => $executionToken]);

            if ($log) {
                $log->setEndTime(new \DateTimeImmutable())
                    ->setExitCode($event->getExitCode());

                $this->entityManager->persist($log);
                $this->entityManager->flush();
            }
        } catch (\Throwable $e) {
            // Silently fail - we don't want logging issues to break the command execution
        } finally {
            // Always clean up the token to prevent memory leaks
            $this->commandExecutionTracker->clearToken($command);
        }
    }
}
