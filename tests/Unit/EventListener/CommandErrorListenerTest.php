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

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandErrorListener;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Ayaou\CommandLoggerBundle\Util\SupportedCommandResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandErrorListenerTest extends TestCase
{
    private CommandErrorListener $listener;

    private MockObject|CommandLogWriter $writer;

    private MockObject|CommandExecutionTracker $commandExecutionTracker;

    private ConsoleErrorEvent $event;

    private Command $command;

    private MockObject|InputInterface $input;

    private MockObject|OutputInterface $output;

    protected function setUp(): void
    {
        $this->writer = $this->createMock(CommandLogWriter::class);
        $this->commandExecutionTracker = $this->createMock(CommandExecutionTracker::class);
        $this->command = new TestCommand();
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);

        $error = new \Exception('Test error');
        $this->event = new ConsoleErrorEvent($this->input, $this->output, $error, $this->command);

        $this->listener = new CommandErrorListener(
            $this->writer,
            $this->commandExecutionTracker,
            true, // Enabled by default
            new SupportedCommandResolver([]),
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandErrorListener($this->writer, $this->commandExecutionTracker, false, new SupportedCommandResolver([]));
        $this->writer->expects($this->never())->method('markErrored');

        $listener->onConsoleError($this->event);
    }

    public function testDoesNothingWhenNoCommand(): void
    {
        $this->event = new ConsoleErrorEvent($this->input, $this->output, new \Exception('Test error'), null);
        $this->writer->expects($this->never())->method('markErrored');

        $this->listener->onConsoleError($this->event);
    }

    public function testDoesNothingWhenNoExecutionToken(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn(null);
        $this->writer->expects($this->never())->method('markErrored');

        $this->listener->onConsoleError($this->event);
    }

    public function testUpdatesLogWhenErrorOccurs(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');

        $this->writer->expects($this->once())->method('markErrored')
            ->with('some-token', $this->callback('is_string'));

        $this->listener->onConsoleError($this->event);
    }

    public function testErrorDetailsAreFormattedCorrectly(): void
    {
        $error = new \Exception('Main error', 0, new \Exception('Previous error'));
        $reflection = new \ReflectionClass($this->listener);
        $method = $reflection->getMethod('getErrorDetails');

        $result = $method->invoke($this->listener, $error);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Main error', $result[0]);
        $this->assertStringContainsString('Previous error', $result[1]);
    }

    public function testGetErrorMessage(): void
    {
        $log = new CommandLog();
        $log->setErrorMessage('Test error message');

        $this->assertEquals('Test error message', $log->getErrorMessage());
        $this->assertNull($log->getId());
    }

    public function testLongErrorMessageIsTruncatedAndSuffixed(): void
    {
        $listener = new CommandErrorListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            new SupportedCommandResolver([]),
            100, // small limit to make the truncation easy to assert on
        );

        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');

        $storedMessage = null;
        $this->writer->expects($this->once())->method('markErrored')
            ->with(
                'some-token',
                $this->callback(function (string $message) use (&$storedMessage) {
                    $storedMessage = $message;

                    return true;
                }),
            );

        // The exception message alone, plus its trace, is far longer than the 100-byte limit.
        $error = new \Exception(str_repeat('a', 500));
        $event = new ConsoleErrorEvent($this->input, $this->output, $error, $this->command);

        $listener->onConsoleError($event);

        $this->assertNotNull($storedMessage);
        $this->assertLessThanOrEqual(100, \strlen($storedMessage));
        $this->assertStringEndsWith(' [truncated]', $storedMessage);
    }

    public function testShortErrorMessageIsNotTruncated(): void
    {
        $listener = new CommandErrorListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            new SupportedCommandResolver([]),
            65535,
        );

        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');

        $storedMessage = null;
        $this->writer->expects($this->once())->method('markErrored')
            ->with(
                'some-token',
                $this->callback(function (string $message) use (&$storedMessage) {
                    $storedMessage = $message;

                    return true;
                }),
            );

        $error = new \Exception('Short error');
        $event = new ConsoleErrorEvent($this->input, $this->output, $error, $this->command);

        $listener->onConsoleError($event);

        $this->assertNotNull($storedMessage);
        $this->assertStringContainsString('Short error', $storedMessage);
        $this->assertStringNotContainsString('[truncated]', $storedMessage);
    }

    public function testWriteFailureDoesNotBreakTheCommand(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');

        $this->writer->expects($this->once())->method('markErrored')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->listener->onConsoleError($this->event);

        // Reaching this line proves the exception raised by CommandLogWriter::markErrored() never propagated out.
        $this->addToAssertionCount(1);
    }

    public function testLogsErrorWithCommandNameWhenLoggingFails(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->writer->method('markErrored')
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

        $listener = new CommandErrorListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            new SupportedCommandResolver([]),
            65535,
            $logger,
        );

        $listener->onConsoleError($this->event);
    }

    public function testDoesNotBreakWhenNoLoggerIsConfigured(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->writer->method('markErrored')
            ->willThrowException(new \RuntimeException('no such table: command_log'));

        $listener = new CommandErrorListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            new SupportedCommandResolver([]),
            65535,
            null,
        );

        $listener->onConsoleError($this->event);

        $this->addToAssertionCount(1);
    }

    public function testOriginalExceptionCarriedByTheEventSurvivesALoggingFailure(): void
    {
        $originalError = new \Exception('Business exception the user must see');
        $event = new ConsoleErrorEvent($this->input, $this->output, $originalError, $this->command);

        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->writer->method('markErrored')
            ->willThrowException(new \RuntimeException('no such table: command_log'));

        $this->listener->onConsoleError($event);

        // The listener must never touch $event or the exception it carries: only the write
        // to the log table is allowed to be given up on.
        $this->assertSame($originalError, $event->getError());
        $this->assertSame('Business exception the user must see', $event->getError()->getMessage());
    }
}
