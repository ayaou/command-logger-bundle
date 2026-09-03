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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\Util;

use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

class CommandExecutionTrackerTest extends TestCase
{
    public function testSetAndGetToken(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = $this->createMock(Command::class);

        $token = 'test-token';
        $tracker->setToken($command, $token);

        $this->assertSame($token, $tracker->getToken($command));
    }

    public function testGetTokenReturnsNullIfNotSet(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = $this->createMock(Command::class);

        $this->assertNull($tracker->getToken($command));
    }

    public function testClearTokenRemovesSpecificToken(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = $this->createMock(Command::class);

        $tracker->setToken($command, 'test-token');
        $tracker->clearToken($command);

        $this->assertNull($tracker->getToken($command));
    }

    public function testClearRemovesAllTokens(): void
    {
        $tracker = new CommandExecutionTracker();
        $command1 = $this->createMock(Command::class);
        $command2 = $this->createMock(Command::class);

        $tracker->setToken($command1, 'token1');
        $tracker->setToken($command2, 'token2');

        $tracker->clear();

        $this->assertNull($tracker->getToken($command1));
        $this->assertNull($tracker->getToken($command2));
    }

    public function testSetAndGetStartTimestamp(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = $this->createMock(Command::class);

        $tracker->setStartTimestamp($command, 123456789);

        $this->assertSame(123456789, $tracker->getStartTimestamp($command));
    }

    public function testGetStartTimestampReturnsNullIfNotSet(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = $this->createMock(Command::class);

        $this->assertNull($tracker->getStartTimestamp($command));
    }

    public function testClearTokenAlsoRemovesStartTimestamp(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = $this->createMock(Command::class);

        $tracker->setToken($command, 'test-token');
        $tracker->setStartTimestamp($command, 123456789);

        $tracker->clearToken($command);

        $this->assertNull($tracker->getStartTimestamp($command));
    }

    public function testClearAlsoRemovesAllStartTimestamps(): void
    {
        $tracker = new CommandExecutionTracker();
        $command1 = $this->createMock(Command::class);
        $command2 = $this->createMock(Command::class);

        $tracker->setStartTimestamp($command1, 111);
        $tracker->setStartTimestamp($command2, 222);

        $tracker->clear();

        $this->assertNull($tracker->getStartTimestamp($command1));
        $this->assertNull($tracker->getStartTimestamp($command2));
    }
}
