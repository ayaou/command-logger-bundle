<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\AbstractCommandListener;
use Ayaou\CommandLoggerBundle\EventListener\CommandErrorListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandErrorListenerTest extends TestCase
{
    private CommandErrorListener              $listener;
    private MockObject|EntityManagerInterface $entityManager;
    private ConsoleErrorEvent                 $event;
    private MockObject|Command                $command;
    private MockObject|InputInterface         $input;
    private MockObject|OutputInterface        $output;
    private MockObject|EntityRepository       $repository;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command       = $this->createMock(Command::class);
        $this->input         = $this->createMock(InputInterface::class);
        $this->output        = $this->createMock(OutputInterface::class);
        $this->repository    = $this->createMock(EntityRepository::class);

        $error       = new \Exception('Test error');
        $this->event = new ConsoleErrorEvent($this->input, $this->output, $error, $this->command);

        $this->entityManager->method('getRepository')
            ->with(CommandLog::class)
            ->willReturn($this->repository);

        $this->listener = new CommandErrorListener(
            $this->entityManager,
            true,
            true
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandErrorListener($this->entityManager, false, true);
        $this->input->expects($this->never())->method('getOption');

        $listener->onConsoleError($this->event);
    }

    public function testDoesNothingWhenLogErrorsDisabled(): void
    {
        $listener = new CommandErrorListener($this->entityManager, true, false);
        $this->input->expects($this->never())->method('getOption');

        $listener->onConsoleError($this->event);
    }

    public function testDoesNothingWhenNoExecutionToken(): void
    {
        $this->input->method('getOption')
            ->with(CommandErrorListener::TOKEN_OPTION_NAME)
            ->willReturn(null);
        $this->entityManager->expects($this->never())->method('getRepository');

        $this->listener->onConsoleError($this->event);
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

        $this->listener->onConsoleError($this->event);
    }

    public function testLogsErrorWithSingleException(): void
    {
        $innerError  = new \RuntimeException('Inner error');
        $this->event = new ConsoleErrorEvent($this->input, $this->output, $innerError, $this->command);

        $log = new CommandLog();
        $this->input->method('getOption')
            ->with(AbstractCommandListener::TOKEN_OPTION_NAME)
            ->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn($log);
        $this->entityManager->expects($this->once())->method('persist')
            ->with(
                $this->callback(function (CommandLog $persistedLog) use ($log) {
                    $errorMessage = $persistedLog->getErrorMessage();
                    return $persistedLog === $log &&
                        !str_contains($errorMessage, 'Outer error') &&
                        str_contains($errorMessage, 'Inner error') &&
                        !str_contains($errorMessage, "\n\n\n");
                })
            );
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->onConsoleError($this->event);
    }

    public function testLogsErrorWithNestedExceptions(): void
    {
        $innerError  = new \RuntimeException('Inner error');
        $outerError  = new \Exception('Outer error', 0, $innerError);
        $this->event = new ConsoleErrorEvent($this->input, $this->output, $outerError, $this->command);

        $log = new CommandLog();
        $this->input->method('getOption')
            ->with(AbstractCommandListener::TOKEN_OPTION_NAME)
            ->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn($log);
        $this->entityManager->expects($this->once())->method('persist')
            ->with(
                $this->callback(function (CommandLog $persistedLog) use ($log) {
                    $errorMessage = $persistedLog->getErrorMessage();
                    return $persistedLog === $log &&
                        str_contains($errorMessage, 'Outer error') &&
                        str_contains($errorMessage, 'Inner error') &&
                        str_contains($errorMessage, "\n\n\n");
                })
            );
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->onConsoleError($this->event);
    }
}