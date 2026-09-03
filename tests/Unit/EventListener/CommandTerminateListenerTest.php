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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandTerminateListener;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Ayaou\CommandLoggerBundle\Util\OutputCapture;
use Ayaou\CommandLoggerBundle\Util\SupportedCommandResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class CommandTerminateListenerTest extends TestCase
{
    private CommandTerminateListener $listener;

    private MockObject|CommandLogWriter $writer;

    private MockObject|CommandExecutionTracker $commandExecutionTracker;

    private ConsoleTerminateEvent $event;

    private Command $command;

    private MockObject|InputInterface $input;

    private MockObject|OutputInterface $output;

    protected function setUp(): void
    {
        $this->writer = $this->createMock(CommandLogWriter::class);
        $this->commandExecutionTracker = $this->createMock(CommandExecutionTracker::class);
        $this->command = new TestCommand();
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(BufferedOutput::class);

        $this->event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 0);

        $this->listener = new CommandTerminateListener(
            $this->writer,
            $this->commandExecutionTracker,
            true, // Enabled by default
            new SupportedCommandResolver([]),
            new OutputCapture(),
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandTerminateListener($this->writer, $this->commandExecutionTracker, false, new SupportedCommandResolver([]), new OutputCapture());
        $this->writer->expects($this->never())->method('markTerminated');
        $this->commandExecutionTracker->expects($this->never())->method('getToken');

        $listener->onConsoleTerminate($this->event);
    }

    public function testDoesNothingWhenNoExecutionToken(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn(null);
        $this->writer->expects($this->never())->method('markTerminated');
        $this->commandExecutionTracker->expects($this->never())->method('clearToken');

        $this->listener->onConsoleTerminate($this->event);
    }

    public function testLogsTerminationAndClearsToken(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');

        $this->writer->expects($this->once())->method('markTerminated')
            ->with(
                'some-token',
                $this->isInstanceOf(\DateTimeImmutable::class),
                0,
                $this->anything(),
            );
        $this->commandExecutionTracker->expects($this->once())->method('clearToken')->with($this->command);

        $this->listener->onConsoleTerminate($this->event);
    }

    public function testWriteFailureDoesNotBreakTheCommand(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');

        $this->writer->expects($this->once())->method('markTerminated')
            ->willThrowException(new \RuntimeException('Connection refused'));

        // The token must still be cleared: the write failed, but tracking state must not leak.
        $this->commandExecutionTracker->expects($this->once())->method('clearToken')->with($this->command);

        $this->listener->onConsoleTerminate($this->event);

        // Reaching this line proves the exception raised by CommandLogWriter::markTerminated() never propagated out.
        $this->addToAssertionCount(1);
    }

    public function testLogsErrorWithCommandNameWhenLoggingFails(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->writer->method('markTerminated')
            ->willThrowException(new \RuntimeException('no such table: command_log'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with(
                $this->stringContains('app:my-command'),
                $this->callback(function (array $context) {
                    return 'app:my-command' === ($context['command'] ?? null)
                        && ($context['exception'] ?? null) instanceof \Throwable;
                }),
            );

        $listener = new CommandTerminateListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            new SupportedCommandResolver([]),
            new OutputCapture(),
            $logger,
        );

        $listener->onConsoleTerminate($this->event);
    }

    public function testDoesNotBreakWhenNoLoggerIsConfigured(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->writer->method('markTerminated')
            ->willThrowException(new \RuntimeException('no such table: command_log'));

        $listener = new CommandTerminateListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            new SupportedCommandResolver([]),
            new OutputCapture(),
            null,
        );

        $listener->onConsoleTerminate($this->event);

        $this->addToAssertionCount(1);
    }
}
