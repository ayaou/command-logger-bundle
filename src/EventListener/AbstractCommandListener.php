<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Command\Command;

class AbstractCommandListener
{
    protected function isSupportedCommand(Command $command): bool
    {
        $commandName = $command->getName();
        if (null === $commandName) {
            return false;
        }
        // Use reflection to check for the CommandLogger attribute
        $reflection = new \ReflectionClass($command);
        $attributes = $reflection->getAttributes(CommandLogger::class);

        // Return true if the attribute exists, false otherwise
        return !empty($attributes);
    }
}
