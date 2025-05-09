<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

class AbstractCommandListener
{
    private iterable $commandMap;

    public function __construct(iterable $commandMap)
    {
        $this->commandMap = $commandMap;
    }

    protected function isSupportedCommand(Command $command, array $otherCommands): bool
    {
        if ($command->getName() && in_array($command->getName(), $otherCommands, true)) {
            return true;
        }

        return $this->hasCommandLoggerAttribute($command);
    }

    private function hasCommandLoggerAttribute(Command $command): bool
    {
        $name = $command->getName();
        if (!$name) {
            return false;
        }

        $application = $command->getApplication();
        if ($application) {
            $command = $application->get($name);
        }

        if ($command instanceof LazyCommand) {
            return $this->hasAttribute($name);
        }

        $reflection = new \ReflectionClass($command);
        $attributes = $reflection->getAttributes(CommandLogger::class);

        return !empty($attributes);
    }

    private function hasAttribute(string $commandName): bool
    {
        foreach ($this->commandMap as $serviceId => $commandInstance) {
            $reflection = new \ReflectionClass($commandInstance);
            $attributes = $reflection->getAttributes(\Symfony\Component\Console\Attribute\AsCommand::class);
            foreach ($attributes as $attribute) {
                $asCommand = $attribute->newInstance();
                if ($asCommand->name === $commandName) {
                    $loggerAttributes = $reflection->getAttributes(CommandLogger::class);

                    return !empty($loggerAttributes);
                }
            }
        }

        return false;
    }
}
