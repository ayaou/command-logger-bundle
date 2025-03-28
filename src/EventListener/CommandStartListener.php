<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Uid\Uuid;

class CommandStartListener extends AbstractCommandListener
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

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$this->enabled || !$command || !$this->isSupportedCommand($command)) {
            return;
        }

        $input          = $event->getInput();
        $log            = new CommandLog();
        $executionToken = Uuid::v4()->toRfc4122();

        $this->commandExecutionTracker->setToken($command, $executionToken);

        $log->setCommandName($command->getName())
            ->setArguments($input->getArguments() + $input->getOptions())
            ->setStartTime(new \DateTimeImmutable())
            ->setExecutionToken($executionToken);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
