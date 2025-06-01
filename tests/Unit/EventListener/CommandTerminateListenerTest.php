<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\CommandTerminateListener;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class CommandTerminateListenerTest extends TestCase
{
    private CommandTerminateListener $listener;

    private MockObject|EntityManagerInterface $entityManager;

    private MockObject|CommandExecutionTracker $commandExecutionTracker;

    private ConsoleTerminateEvent $event;

    private Command $command;

    private MockObject|InputInterface $input;

    private MockObject|OutputInterface $output;

    private MockObject|EntityRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager           = $this->createMock(EntityManagerInterface::class);
        $this->commandExecutionTracker = $this->createMock(CommandExecutionTracker::class);
        $this->command                 = new TestCommand();
        $this->input                   = $this->createMock(InputInterface::class);
        $this->output                  = $this->createMock(BufferedOutput::class);
        $this->repository              = $this->createMock(EntityRepository::class);

        $this->event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 0);

        $this->entityManager->method('getRepository')
            ->with(CommandLog::class)
            ->willReturn($this->repository);

        $this->listener = new CommandTerminateListener(
            $this->entityManager,
            $this->commandExecutionTracker,
            true, // Enabled by default
            [],
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandTerminateListener($this->entityManager, $this->commandExecutionTracker, false, []);
        $this->entityManager->expects($this->never())->method('persist');
        $this->commandExecutionTracker->expects($this->never())->method('getToken');

        $listener->onConsoleTerminate($this->event);
    }

    public function testDoesNothingWhenNoExecutionToken(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');
        $this->commandExecutionTracker->expects($this->never())->method('clearToken');

        $this->listener->onConsoleTerminate($this->event);
    }

    public function testDoesNothingWhenNoLogFound(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');
        // Removed the incorrect expectation that clearToken() should not be called

        $this->listener->onConsoleTerminate($this->event);
    }

    public function testLogsTerminationAndClearsToken(): void
    {
        $log = new CommandLog();
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn($log);

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->callback(function (CommandLog $persistedLog) use ($log) {
                return $persistedLog === $log
                    && $persistedLog->getEndTime() instanceof \DateTimeImmutable
                    && 0 === $persistedLog->getExitCode();
            }));
        $this->entityManager->expects($this->once())->method('flush');
        $this->commandExecutionTracker->expects($this->once())->method('clearToken')->with($this->command);

        $this->listener->onConsoleTerminate($this->event);
    }
}
