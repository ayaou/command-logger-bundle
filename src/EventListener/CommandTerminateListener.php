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

    public function __construct(
        EntityManagerInterface $entityManager,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
    ) {
        $this->entityManager           = $entityManager;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled                 = $enabled;
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->enabled || !$this->isSupportedCommand($command)) {
            return;
        }

        $executionToken = $this->commandExecutionTracker->getToken($command);
        if (!$executionToken) {
            return;
        }

        $log = $this->entityManager->getRepository(CommandLog::class)
            ->findOneBy(['executionToken' => $executionToken]);

        if ($log) {
            $log->setEndTime(new \DateTimeImmutable())
                ->setExitCode($event->getExitCode());

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        }

        $this->commandExecutionTracker->clearToken($command);
    }
}
