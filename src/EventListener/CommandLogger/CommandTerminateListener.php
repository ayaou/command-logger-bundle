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

    /**
     * @param array<int|string, string> $otherCommands
     * @param array<int, string>        $attributedCommands names (and aliases) collected at
     *                                                       compile time by CommandLoggerPass
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
        array $otherCommands = [],
        array $attributedCommands = [],
    ) {
        $this->entityManager = $entityManager;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled = $enabled;
        $this->otherCommands = $otherCommands;
        $this->attributedCommands = $attributedCommands;
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
