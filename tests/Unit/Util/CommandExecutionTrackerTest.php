<?php

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
        $tracker  = new CommandExecutionTracker();
        $command1 = $this->createMock(Command::class);
        $command2 = $this->createMock(Command::class);

        $tracker->setToken($command1, 'token1');
        $tracker->setToken($command2, 'token2');

        $tracker->clear();

        $this->assertNull($tracker->getToken($command1));
        $this->assertNull($tracker->getToken($command2));
    }
}
