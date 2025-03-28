<?php

namespace Ayaou\CommandLoggerBundle\Util;

use Symfony\Component\Console\Command\Command;

class CommandExecutionTracker
{
    private array $tokens = [];

    public function setToken(Command $command, string $token): void
    {
        $this->tokens[spl_object_id($command)] = $token;
    }

    public function getToken(Command $command): ?string
    {
        return $this->tokens[spl_object_id($command)] ?? null;
    }

    public function clearToken(Command $command): void
    {
        unset($this->tokens[spl_object_id($command)]);
    }

    public function clear(): void
    {
        $this->tokens = [];
    }
}
