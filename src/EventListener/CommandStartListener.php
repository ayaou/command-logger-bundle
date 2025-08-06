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

        try {
            $input          = $event->getInput();
            $log            = new CommandLog();
            $executionToken = Uuid::v4()->toRfc4122();

            $this->commandExecutionTracker->setToken($command, $executionToken);

            $log->setCommandName($commandName)
                ->setArguments($this->sanitizeArguments($input->getArguments() + $input->getOptions()))
                ->setStartTime(new \DateTimeImmutable())
                ->setExecutionToken($executionToken);

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            // Silently fail - we don't want logging issues to break the command execution
            // In a real application, you might want to log this to a separate error log
        }
    }

    private function sanitizeArguments(array $arguments): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth'];
        
        foreach ($arguments as $key => $value) {
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($sensitiveKeys as $sensitiveKey) {
                    if (str_contains($lowerKey, $sensitiveKey)) {
                        $arguments[$key] = '[REDACTED]';
                        break;
                    }
                }
            }
        }
        
        return $arguments;
    }
}
