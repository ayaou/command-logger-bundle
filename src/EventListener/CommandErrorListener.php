<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class CommandErrorListener extends AbstractCommandListener
{
    private EntityManagerInterface $entityManager;

    private bool $enabled;

    private bool $logErrors;

    public function __construct(
        EntityManagerInterface $entityManager,
        bool $enabled,
        bool $logErrors
    ) {
        $this->entityManager = $entityManager;
        $this->enabled       = $enabled;
        $this->logErrors     = $logErrors;
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        if (!$this->enabled || !$this->logErrors) {
            return;
        }

        $executionToken = $event->getInput()->getOption(self::TOKEN_OPTION_NAME);
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
        $errorDetails = [$error->getMessage() . "\n" . $error->getTraceAsString()];

        $limit    = 10;
        $previous = $error->getPrevious();
        while ($previous && $limit-- > 0) {
            $errorDetails[] = $previous->getMessage() . "\n" . $previous->getTraceAsString();
            $previous       = $previous->getPrevious();
        }

        return $errorDetails;
    }
}