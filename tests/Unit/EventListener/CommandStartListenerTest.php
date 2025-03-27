<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\AbstractCommandListener;
use Ayaou\CommandLoggerBundle\EventListener\CommandStartListener;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
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

    private ConsoleCommandEvent $event;

    private MockObject|Command $command;

    private MockObject|InputInterface $input;

    private MockObject|OutputInterface $output;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command       = $this->createMock(Command::class);
        $this->input         = $this->createMock(InputInterface::class);
        $this->output        = $this->createMock(OutputInterface::class);
        $this->event         = new ConsoleCommandEvent($this->command, $this->input, $this->output);

        $this->listener = new CommandStartListener(
            $this->entityManager,
            true, // enabled by default
            ['cache:clear'], // excluded_commands
            [], // included_commands
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandStartListener($this->entityManager, false, [], []);
        $this->entityManager->expects($this->never())->method('persist');

        $listener->onConsoleCommand($this->event);
    }

    public function testDoesNothingWhenNoCommand(): void
    {
        $this->event = new ConsoleCommandEvent(null, $this->input, $this->output);
        $this->entityManager->expects($this->never())->method('persist');

        $this->listener->onConsoleCommand($this->event);
    }

    public function testSkipsExcludedCommand(): void
    {
        $this->command->method('getName')->willReturn('cache:clear');
        $this->entityManager->expects($this->never())->method('persist');

        $this->listener->onConsoleCommand($this->event);
    }

    public function testSkipsCommandNotInIncludedList(): void
    {
        $listener = new CommandStartListener($this->entityManager, true, [], ['app:my-command']);
        $this->command->method('getName')->willReturn('app:other-command');
        $this->entityManager->expects($this->never())->method('persist');

        $listener->onConsoleCommand($this->event);
    }

    public function testLogsIncludedCommand(): void
    {
        $listener = new CommandStartListener($this->entityManager, true, [], ['app:my-command']);
        $this->command->method('getName')->willReturn('app:my-command');
        $this->input->method('getArguments')->willReturn(['arg1' => 'value1']);
        $this->input->method('getOptions')->willReturn(['opt1' => 'value2']);

        $this->entityManager->expects($this->once())->method('persist')->with(
            $this->callback(function (CommandLog $log) {
                return 'app:my-command' === $log->getCommandName()
                    && $log->getArguments() === ['arg1' => 'value1', 'opt1' => 'value2']
                    && $log->getStartTime() instanceof \DateTimeImmutable
                    && !empty($log->getExecutionToken());
            }),
        );
        $this->entityManager->expects($this->once())->method('flush');
        $this->input->expects($this->once())->method('setOption')
            ->with(
                AbstractCommandListener::TOKEN_OPTION_NAME,
                $this->callback(function ($token) {
                    return Uuid::isValid($token);
                }),
            );

        $listener->onConsoleCommand($this->event);
    }

    public function testLogsNonExcludedCommand(): void
    {
        $this->command->method('getName')->willReturn('app:my-command');
        $this->input->method('getArguments')->willReturn(['arg1' => 'value1']);
        $this->input->method('getOptions')->willReturn(['opt1' => 'value2']);

        $this->entityManager->expects($this->once())->method('persist')->with(
            $this->callback(function (CommandLog $log) {
                return 'app:my-command' === $log->getCommandName()
                    && $log->getArguments() === ['arg1' => 'value1', 'opt1' => 'value2']
                    && $log->getStartTime() instanceof \DateTimeImmutable
                    && !empty($log->getExecutionToken());
            }),
        );
        $this->entityManager->expects($this->once())->method('flush');
        $this->input->expects($this->once())->method('setOption')
            ->with(
                AbstractCommandListener::TOKEN_OPTION_NAME,
                $this->callback(function ($token) {
                    return Uuid::isValid($token);
                }),
            );

        $this->listener->onConsoleCommand($this->event);
    }

    public function testLogsCommandWithEmptyArgumentsAndOptions(): void
    {
        $this->command->method('getName')->willReturn('app:my-command');
        $this->input->method('getArguments')->willReturn([]);
        $this->input->method('getOptions')->willReturn([]);

        $this->entityManager->expects($this->once())->method('persist')->with(
            $this->callback(function (CommandLog $log) {
                return 'app:my-command' === $log->getCommandName()
                    && null === $log->getId()
                    && [] === $log->getArguments()
                    && $log->getStartTime() instanceof \DateTimeImmutable
                    && !empty($log->getExecutionToken());
            }),
        );
        $this->entityManager->expects($this->once())->method('flush');
        $this->input->expects($this->once())->method('setOption')
            ->with(
                AbstractCommandListener::TOKEN_OPTION_NAME,
                $this->callback(function ($token) {
                    return Uuid::isValid($token);
                }),
            );

        $this->listener->onConsoleCommand($this->event);
    }
}
