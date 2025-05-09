<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\CommandStartListener;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

class CommandStartListenerTest extends TestCase
{
    private CommandStartListener $listener;

    private MockObject|EntityManagerInterface $entityManager;

    private MockObject|CommandExecutionTracker $commandExecutionTracker;

    private ConsoleCommandEvent $event;

    private Command $command;

    private MockObject|InputInterface $input;

    private MockObject|OutputInterface $output;

    protected function setUp(): void
    {
        $this->entityManager           = $this->createMock(EntityManagerInterface::class);
        $this->commandExecutionTracker = $this->createMock(CommandExecutionTracker::class);
        $this->command                 = new TestCommand();
        $this->input                   = $this->createMock(InputInterface::class);
        $this->output                  = $this->createMock(OutputInterface::class);
        $this->event                   = new ConsoleCommandEvent($this->command, $this->input, $this->output);

        $this->listener = new CommandStartListener(
            $this->entityManager,
            $this->commandExecutionTracker,
            [],
            true, // Enabled by default
            [],
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandStartListener($this->entityManager, $this->commandExecutionTracker, [], false, []);
        $this->entityManager->expects($this->never())->method('persist');
        $this->commandExecutionTracker->expects($this->never())->method('setToken');

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenUsedWithNonConfiguredCommand(): void
    {
        $command     = new TestCommandWithoutAttribute();
        $this->event = new ConsoleCommandEvent($command, $this->input, $this->output);

        $listener = new CommandStartListener($this->entityManager, $this->commandExecutionTracker, [], true, []);
        $this->entityManager->expects($this->never())->method('persist');
        $this->commandExecutionTracker->expects($this->never())->method('setToken');

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenUsedWithConfiguredCommand(): void
    {
        $command     = new TestCommandWithoutAttribute();
        $this->event = new ConsoleCommandEvent($command, $this->input, $this->output);

        $listener = new CommandStartListener($this->entityManager, $this->commandExecutionTracker, [], true, ['app:command-without-attribute']);
        $this->entityManager->expects($this->once())->method('persist');
        $this->commandExecutionTracker->expects($this->once())->method('setToken');

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenNoCommand(): void
    {
        $this->event = new ConsoleCommandEvent(null, $this->input, $this->output);
        $this->entityManager->expects($this->never())->method('persist');
        $this->commandExecutionTracker->expects($this->never())->method('setToken');

        $this->listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenCommandHasNoName(): void
    {
        $command = new TestCommandWithoutName();

        $this->event = new ConsoleCommandEvent($command, $this->input, $this->output);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
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

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->callback(function (CommandLog $log) {
                return 'app:my-command' === $log->getCommandName()
                    && $log->getArguments() === ['arg1' => 'value1', 'opt1' => 'value2']
                    && $log->getStartTime() instanceof \DateTimeImmutable
                    && Uuid::isValid($log->getExecutionToken());
            }));

        $this->entityManager->expects($this->once())->method('flush');

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

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->callback(function (CommandLog $log) {
                return 'app:my-command' === $log->getCommandName()
                    && [] === $log->getArguments()
                    && $log->getStartTime() instanceof \DateTimeImmutable
                    && Uuid::isValid($log->getExecutionToken());
            }));
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->onConsoleCommand($this->event);
    }
}
