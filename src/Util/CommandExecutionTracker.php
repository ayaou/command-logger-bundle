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

    /**
     * Start instants, in nanoseconds from hrtime(true), keyed the same way as $tokens. Kept
     * separate from the CommandLog entity on purpose: CommandTerminateListener reloads the
     * log row from the database, whose datetime_immutable columns only store to the second,
     * so `endTime - startTime` would always be a multiple of 1000ms. This table carries the
     * real start instant across the request in memory instead.
     *
     * @var array<int, int>
     */
    private array $startTimestamps = [];

    public function setToken(Command $command, string $token): void
    {
        $this->tokens[spl_object_id($command)] = $token;
    }

    public function getToken(Command $command): ?string
    {
        return $this->tokens[spl_object_id($command)] ?? null;
    }

    /**
     * Records the instant a command started, in nanoseconds. Callers should pass
     * hrtime(true): it is monotonic, unlike microtime(), which can move backwards if the
     * system clock is adjusted mid-execution.
     */
    public function setStartTimestamp(Command $command, int $timestamp): void
    {
        $this->startTimestamps[spl_object_id($command)] = $timestamp;
    }

    public function getStartTimestamp(Command $command): ?int
    {
        return $this->startTimestamps[spl_object_id($command)] ?? null;
    }

    public function clearToken(Command $command): void
    {
        $id = spl_object_id($command);

        unset($this->tokens[$id], $this->startTimestamps[$id]);
    }

    public function clear(): void
    {
        $this->tokens = [];
        $this->startTimestamps = [];
    }
}
