<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Uid\Uuid;

class CommandStartListener extends AbstractCommandListener
{
    private EntityManagerInterface $entityManager;

    private bool $enabled;

    private array $excludedCommands;

    private array $includedCommands;

    public function __construct(
        EntityManagerInterface $entityManager,
        bool $enabled,
        array $excludedCommands,
        array $includedCommands
    ) {
        $this->entityManager    = $entityManager;
        $this->enabled          = $enabled;
        $this->excludedCommands = $excludedCommands;
        $this->includedCommands = $includedCommands;
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $command = $event->getCommand();
        if (!$command) {
            return;
        }

        $commandName = $command->getName();
        if (!empty($this->includedCommands)) {
            if (!in_array($commandName, $this->includedCommands, true)) {
                return;
            }
        } elseif (in_array($commandName, $this->excludedCommands, true)) {
            return;
        }

        $input          = $event->getInput();
        $log            = new CommandLog();
        $executionToken = Uuid::v4()->toRfc4122();

        $log->setCommandName($commandName)
            ->setArguments($input->getArguments() + $input->getOptions())
            ->setStartTime(new \DateTimeImmutable())
            ->setExecutionToken($executionToken);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $event->getInput()->setOption(self::TOKEN_OPTION_NAME, $executionToken);
    }
}