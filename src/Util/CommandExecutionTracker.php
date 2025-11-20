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

namespace Ayaou\CommandLoggerBundle\Util;

use Symfony\Component\Console\Command\Command;

class CommandExecutionTracker
{
    /**
     * @var array<int, string>
     */
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
