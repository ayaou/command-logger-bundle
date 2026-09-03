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

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Command\Command;

class AbstractCommandListener
{
    /**
     * @param array<int|string, string> $otherCommands
     * @param array<int, string>        $attributedCommands names (and aliases) collected at compile
     *                                                      time by CommandLoggerPass from every
     *                                                      #[CommandLogger] class - this is what
     *                                                      covers invokable-style commands, whose
     *                                                      runtime Command instance has been
     *                                                      rewritten by Symfony's AddConsoleCommandPass
     *                                                      and can no longer be reflected upon directly
     */
    protected function isSupportedCommand(Command $command, array $otherCommands, array $attributedCommands = []): bool
    {
        $name = $command->getName();
        if (!$name) {
            return false;
        }

        if ($this->isSupportedOnConfig($name, $otherCommands)) {
            return true;
        }

        if (in_array($name, $attributedCommands, true)) {
            return true;
        }

        // Reflection-based fallback: covers a Command subclass whose name is set in
        // configure() rather than via #[AsCommand] (CommandLoggerPass has no name to read for
        // it), and a command instantiated directly on an Application without ever going
        // through the container (never tagged "console.command", so CommandLoggerPass never
        // sees it either).
        return $this->hasCommandLoggerAttribute($command);
    }

    private function hasCommandLoggerAttribute(Command $command): bool
    {
        $reflection = new \ReflectionClass($command);
        $attributes = $reflection->getAttributes(CommandLogger::class);

        return !empty($attributes);
    }

    /**
     * @param array<string> $otherCommands
     */
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
