<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\AbstractCommandListener;
use Ayaou\CommandLoggerBundle\EventListener\CommandTerminateListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\Exception;
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
    private ConsoleTerminateEvent $event;
    private MockObject|Command $command;
    private MockObject|InputInterface $input;
    private MockObject|OutputInterface $output;
    private MockObject|EntityRepository $repository;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = $this->createMock(Command::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(BufferedOutput::class);
        $this->repository = $this->createMock(EntityRepository::class);

        $this->event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 0);

        $this->entityManager->method('getRepository')
            ->with(CommandLog::class)
            ->willReturn($this->repository);

        $this->listener = new CommandTerminateListener(
            $this->entityManager,
            true, // enabled by default
            true  // logOutput by default
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandTerminateListener($this->entityManager, false, true);
        $this->input->expects($this->never())->method('getOption');

        $listener->onConsoleTerminate($this->event);
    }

    public function testDoesNothingWhenNoExecutionToken(): void
    {
        $this->input->method('getOption')
            ->with(AbstractCommandListener::TOKEN_OPTION_NAME)
            ->willReturn(null);
        $this->entityManager->expects($this->never())->method('getRepository');

        $this->listener->onConsoleTerminate($this->event);
    }

    public function testDoesNothingWhenNoLogFound(): void
    {
        $this->input->method('getOption')
            ->with(AbstractCommandListener::TOKEN_OPTION_NAME)
            ->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');

        $this->listener->onConsoleTerminate($this->event);
    }

    public function testLogsTerminationWithoutOutput(): void
    {
        $log = new CommandLog();
        $listener = new CommandTerminateListener($this->entityManager, true, false); // logOutput off

        $this->input->method('getOption')
            ->with(AbstractCommandListener::TOKEN_OPTION_NAME)
            ->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn($log);

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->callback(function (CommandLog $persistedLog) use ($log) {
                return $persistedLog === $log &&
                    $persistedLog->getEndTime() instanceof \DateTimeImmutable &&
                    $persistedLog->getExitCode() === 0 &&
                    $persistedLog->getOutput() === null;
            }));
        $this->entityManager->expects($this->once())->method('flush');

        $listener->onConsoleTerminate($this->event);
    }

    public function testLogsTerminationWithOutputWhenFetchExists(): void
    {
        $log = new CommandLog();

        $this->input->method('getOption')
            ->with(AbstractCommandListener::TOKEN_OPTION_NAME)
            ->willReturn('some-token');

        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn($log);

        $this->output->expects($this->once())->method('fetch')->willReturn('command output');

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->callback(function (CommandLog $persistedLog) use ($log) {
                return $persistedLog === $log &&
                    $persistedLog->getEndTime() instanceof \DateTimeImmutable &&
                    $persistedLog->getExitCode() === 0 &&
                    $persistedLog->getOutput() === 'command output';
            }));
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->onConsoleTerminate($this->event);
    }

    public function testLogsTerminationWithoutOutputWhenFetchMissing(): void
    {
        $log = new CommandLog();
        $this->input->method('getOption')
            ->with(AbstractCommandListener::TOKEN_OPTION_NAME)
            ->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn($log);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (CommandLog $persistedLog) use ($log) {
                return
                    $persistedLog->getEndTime() instanceof \DateTimeImmutable &&
                    $persistedLog->getExitCode() === 0 &&
                    $persistedLog->getOutput() === '';
            }));
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->onConsoleTerminate($this->event);
    }
}