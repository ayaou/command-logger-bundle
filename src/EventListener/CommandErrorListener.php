<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class CommandErrorListener extends AbstractCommandListener
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

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->enabled || !$command || !$this->isSupportedCommand($command)) {
            return;
        }

        $executionToken = $this->commandExecutionTracker->getToken($command);
        if (!$executionToken) {
            return;
        }

        $log = $this->entityManager->getRepository(CommandLog::class)
            ->findOneBy(['executionToken' => $executionToken]);

        if ($log) {
            $errorDetails = $this->getErrorDetails($event->getError());
            $log->setErrorMessage(implode("\n\n\n", $errorDetails));

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        }
    }

    private function getErrorDetails(\Throwable $error): array
    {
        $errorDetails = [$error->getMessage()."\n".$error->getTraceAsString()];

        $limit    = 10;
        $previous = $error->getPrevious();
        while ($previous && $limit-- > 0) {
            $errorDetails[] = $previous->getMessage()."\n".$previous->getTraceAsString();
            $previous       = $previous->getPrevious();
        }

        return $errorDetails;
    }
}
