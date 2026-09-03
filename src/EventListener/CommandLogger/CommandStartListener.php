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
use Psr\Log\LoggerInterface;
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
        array $otherCommands,
        SensitiveParameterRedactor $sensitiveParameterRedactor,
        array $attributedCommands = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->entityManager = $entityManager;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled = $enabled;
        $this->otherCommands = $otherCommands;
        $this->sensitiveParameterRedactor = $sensitiveParameterRedactor;
        $this->attributedCommands = $attributedCommands;
        $this->logger = $logger;
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$this->enabled || !$command || !$this->isSupportedCommand($command, $this->otherCommands, $this->attributedCommands)) {
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
        $this->commandExecutionTracker->setStartTimestamp($command, hrtime(true));

        // SensitiveParameterRedactor::redact() keeps a wider array<int|string, mixed> signature
        // to stay reusable, but Console argument and option names are always strings - narrow
        // the type back down here for the CommandLog::setArguments() call below.
        /** @var array<string, mixed> $arguments */
        $arguments = $this->sensitiveParameterRedactor->redact($input->getArguments() + $input->getOptions());

        $log->setCommandName($commandName)
            ->setArguments($arguments)
            ->setStartTime(new \DateTimeImmutable())
            ->setExecutionToken($executionToken);

        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            // A logging failure must never take the user's command down with it: give up on
            // writing this entry and let the command run its course.
            $this->logger?->error(
                sprintf('Command logger bundle failed to log the start of command "%s".', $commandName),
                ['command' => $commandName, 'exception' => $exception],
            );
        }
    }
}
