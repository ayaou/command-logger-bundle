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
use Ayaou\CommandLoggerBundle\Util\SensitiveParameterRedactor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Uid\Uuid;

class CommandStartListener extends AbstractCommandListener
{
    private EntityManagerInterface $entityManager;

    private CommandExecutionTracker $commandExecutionTracker;

    private bool $enabled;
    /**
     * @var array<int|string, string>
     */
    private array $otherCommands;

    private SensitiveParameterRedactor $sensitiveParameterRedactor;

    /**
     * @param array<int|string, string> $otherCommands
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
        array $otherCommands,
        SensitiveParameterRedactor $sensitiveParameterRedactor,
    ) {
        $this->entityManager = $entityManager;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled = $enabled;
        $this->otherCommands = $otherCommands;
        $this->sensitiveParameterRedactor = $sensitiveParameterRedactor;
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$this->enabled || !$command || !$this->isSupportedCommand($command, $this->otherCommands)) {
            return;
        }

        $commandName = $command->getName();
        if (!$commandName) {
            return;
        }

        $input = $event->getInput();
        $log = new CommandLog();
        $executionToken = Uuid::v7()->toRfc4122();

        $this->commandExecutionTracker->setToken($command, $executionToken);

        $arguments = $this->sensitiveParameterRedactor->redact($input->getArguments() + $input->getOptions());

        $log->setCommandName($commandName)
            ->setArguments($arguments)
            ->setStartTime(new \DateTimeImmutable())
            ->setExecutionToken($executionToken);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
