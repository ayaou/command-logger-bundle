<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

class CommandTerminateListener extends AbstractCommandListener
{
    private EntityManagerInterface $entityManager;

    private bool $enabled;

    private bool $logOutput;

    public function __construct(
        EntityManagerInterface $entityManager,
        bool $enabled,
        bool $logOutput
    ) {
        $this->entityManager = $entityManager;
        $this->enabled       = $enabled;
        $this->logOutput     = $logOutput;
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $executionToken = $event->getInput()->getOption(self::TOKEN_OPTION_NAME);
        if (!$executionToken) {
            return;
        }

        $log = $this->entityManager->getRepository(CommandLog::class)
            ->findOneBy(['executionToken' => $executionToken]);

        if ($log) {
            if ($this->logOutput) {
                $output = $event->getOutput();
                if (method_exists($output, 'fetch')) {
                    $log->setOutput($output->fetch());
                }
            }

            $log->setEndTime(new \DateTimeImmutable())
                ->setExitCode($event->getExitCode());

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        }
    }
}