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
        $name = $command->getName();
        if (!$name) {
            return false;
        }

        if ($this->isSupportedOnConfig($name, $otherCommands)) {
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

    private function isSupportedOnConfig(string $name, array $otherCommands): bool
    {
        if (in_array($name, $otherCommands, true)) {
            return true;
        }

        foreach ($otherCommands as $pattern) {
            if ($this->matchWithWildcard($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    private function matchWithWildcard(string $pattern, string $name): bool
    {
        // Escape special regex characters in the pattern, except for '*'.
        $escapedPattern = preg_quote($pattern, '/');

        // Replace '*' in the pattern with '.*' for regex matching.
        $regex = '/^'.str_replace('\*', '.*', $escapedPattern).'$/';

        // Perform a regex match.
        return (bool) preg_match($regex, $name);
    }
}
