<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\CommandErrorListener;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandErrorListenerTest extends TestCase
{
    private CommandErrorListener $listener;

    private MockObject|EntityManagerInterface $entityManager;

    private MockObject|CommandExecutionTracker $commandExecutionTracker;

    private ConsoleErrorEvent $event;

    private Command $command;

    private MockObject|InputInterface $input;

    private MockObject|OutputInterface $output;

    private MockObject|ObjectRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager           = $this->createMock(EntityManagerInterface::class);
        $this->commandExecutionTracker = $this->createMock(CommandExecutionTracker::class);
        $this->command                 = new TestCommand();
        $this->input                   = $this->createMock(InputInterface::class);
        $this->output                  = $this->createMock(OutputInterface::class);
        $this->repository              = $this->createMock(CommandLogRepository::class); // Changed to EntityRepository

        $error       = new \Exception('Test error');
        $this->event = new ConsoleErrorEvent($this->input, $this->output, $error, $this->command);

        $this->entityManager->method('getRepository')
            ->with(CommandLog::class)
            ->willReturn($this->repository);

        $this->listener = new CommandErrorListener(
            $this->entityManager,
            $this->commandExecutionTracker,
            true, // Enabled by default
            [],
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $listener = new CommandErrorListener($this->entityManager, $this->commandExecutionTracker, false, []);
        $this->entityManager->expects($this->never())->method('getRepository');

        $listener->onConsoleError($this->event);
    }

    public function testDoesNothingWhenNoCommand(): void
    {
        $this->event = new ConsoleErrorEvent($this->input, $this->output, new \Exception('Test error'), null);
        $this->entityManager->expects($this->never())->method('getRepository');

        $this->listener->onConsoleError($this->event);
    }

    public function testDoesNothingWhenNoExecutionToken(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn(null);
        $this->entityManager->expects($this->never())->method('getRepository');

        $this->listener->onConsoleError($this->event);
    }

    public function testDoesNothingWhenNoLogFound(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');
        $this->repository->method('findOneBy')
            ->with(['executionToken' => 'some-token'])
            ->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');

        $this->listener->onConsoleError($this->event);
    }

    public function testUpdatesLogWhenErrorOccurs(): void
    {
        $this->commandExecutionTracker->method('getToken')->with($this->command)->willReturn('some-token');

        $log = $this->createMock(CommandLog::class);
        $this->repository->method('findOneBy')->with(['executionToken' => 'some-token'])->willReturn($log);

        $log->expects($this->once())->method('setErrorMessage');
        $this->entityManager->expects($this->once())->method('persist')->with($log);
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->onConsoleError($this->event);
    }

    public function testErrorDetailsAreFormattedCorrectly(): void
    {
        $error      = new \Exception('Main error', 0, new \Exception('Previous error'));
        $reflection = new \ReflectionClass($this->listener);
        $method     = $reflection->getMethod('getErrorDetails');

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
}
