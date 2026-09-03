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

use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandStartListener;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Ayaou\CommandLoggerBundle\Util\SensitiveParameterRedactor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

class CommandStartListenerTest extends TestCase
{
    private CommandStartListener $listener;

    private MockObject|CommandLogWriter $writer;

    private MockObject|CommandExecutionTracker $commandExecutionTracker;

    private ConsoleCommandEvent $event;

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
        $this->event = new ConsoleCommandEvent($this->command, $this->input, $this->output);

        $this->listener = new CommandStartListener(
            $this->writer,
            $this->commandExecutionTracker,
            true, // Enabled by default
            [],
            new SensitiveParameterRedactor([]),
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandStartListener($this->writer, $this->commandExecutionTracker, false, [], new SensitiveParameterRedactor([]));
        $this->writer->expects($this->never())->method('create');
        $this->commandExecutionTracker->expects($this->never())->method('setToken');

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenUsedWithNonConfiguredCommand(): void
    {
        $command = new TestCommandWithoutAttribute();
        $this->event = new ConsoleCommandEvent($command, $this->input, $this->output);

        $listener = new CommandStartListener($this->writer, $this->commandExecutionTracker, true, [], new SensitiveParameterRedactor([]));
        $this->writer->expects($this->never())->method('create');
        $this->commandExecutionTracker->expects($this->never())->method('setToken');

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenUsedWithConfiguredCommand(): void
    {
        $command = new TestCommandWithoutAttribute();
        $this->event = new ConsoleCommandEvent($command, $this->input, $this->output);

        $listener = new CommandStartListener($this->writer, $this->commandExecutionTracker, true, ['app:command-without-attribute'], new SensitiveParameterRedactor([]));
        $this->writer->expects($this->once())->method('create');
        $this->commandExecutionTracker->expects($this->once())->method('setToken');

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenNoCommand(): void
    {
        $this->event = new ConsoleCommandEvent(null, $this->input, $this->output);
        $this->writer->expects($this->never())->method('create');
        $this->commandExecutionTracker->expects($this->never())->method('setToken');

        $this->listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenCommandHasNoName(): void
    {
        $command = new TestCommandWithoutName();

        $this->event = new ConsoleCommandEvent($command, $this->input, $this->output);

        $this->writer->expects($this->never())->method('create');
        $this->commandExecutionTracker->expects($this->never())->method('setToken');

        $this->listener->onConsoleCommand($this->event);
    }

    public function testLogsCommandWithAttribute(): void
    {
        $this->input->method('getArguments')->willReturn(['arg1' => 'value1']);
        $this->input->method('getOptions')->willReturn(['opt1' => 'value2']);

        $this->commandExecutionTracker->expects($this->once())->method('setToken')
            ->with(
                $this->command,
                $this->callback(function ($token) {
                    return Uuid::isValid($token);
                }),
            );

        $this->writer->expects($this->once())->method('create')
            ->with(
                'app:my-command',
                ['arg1' => 'value1', 'opt1' => 'value2'],
                $this->isInstanceOf(\DateTimeImmutable::class),
                $this->callback(function ($token) {
                    return Uuid::isValid($token);
                }),
            );

        $this->listener->onConsoleCommand($this->event);
    }

    public function testLogsCommandWithEmptyArgumentsAndOptions(): void
    {
        $this->input->method('getArguments')->willReturn([]);
        $this->input->method('getOptions')->willReturn([]);

        $this->commandExecutionTracker->expects($this->once())->method('setToken')
            ->with(
                $this->command,
                $this->callback(function ($token) {
                    return Uuid::isValid($token);
                }),
            );

        $this->writer->expects($this->once())->method('create')
            ->with(
                'app:my-command',
                [],
                $this->isInstanceOf(\DateTimeImmutable::class),
                $this->callback(function ($token) {
                    return Uuid::isValid($token);
                }),
            );

        $this->listener->onConsoleCommand($this->event);
    }

    public function testWriteFailureDoesNotBreakTheCommand(): void
    {
        $this->input->method('getArguments')->willReturn([]);
        $this->input->method('getOptions')->willReturn([]);

        $this->writer->expects($this->once())->method('create')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->listener->onConsoleCommand($this->event);

        // Reaching this line proves the exception raised by CommandLogWriter::create() never propagated out.
        $this->addToAssertionCount(1);
    }

    public function testLogsErrorWithCommandNameWhenWriteFails(): void
    {
        $this->input->method('getArguments')->willReturn([]);
        $this->input->method('getOptions')->willReturn([]);

        $this->writer->method('create')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with(
                $this->stringContains('app:my-command'),
                $this->callback(function (array $context) {
                    return 'app:my-command' === ($context['command'] ?? null)
                        && ($context['exception'] ?? null) instanceof \Throwable;
                }),
            );

        $listener = new CommandStartListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            [],
            new SensitiveParameterRedactor([]),
            [],
            $logger,
        );

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNotBreakWhenNoLoggerIsConfigured(): void
    {
        $this->input->method('getArguments')->willReturn([]);
        $this->input->method('getOptions')->willReturn([]);

        $this->writer->method('create')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $listener = new CommandStartListener(
            $this->writer,
            $this->commandExecutionTracker,
            true,
            [],
            new SensitiveParameterRedactor([]),
            [],
            null,
        );

        $listener->onConsoleCommand($this->event);

        $this->addToAssertionCount(1);
    }
}
