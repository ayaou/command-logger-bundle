<?php

namespace Ayaou\CommandLoggerBundle\EventListener;

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Command\Command;

class AbstractCommandListener
{
    protected function isSupportedCommand(Command $command, array $otherCommands): bool
    {
        if ($command->getName() && in_array($command->getName(), $otherCommands, true)) {
            return true;
        }

        return $this->hasCommandLoggerAttribute($command);
    }

    private function hasCommandLoggerAttribute(Command $command): bool
    {
        $reflection = new \ReflectionClass($command);
        $attributes = $reflection->getAttributes(CommandLogger::class);

        return !empty($attributes);
    }
}
