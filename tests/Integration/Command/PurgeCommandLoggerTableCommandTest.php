<?php

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Command;

use Ayaou\CommandLoggerBundle\Command\PurgeCommandLoggerTableCommand;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeCommandLoggerTableCommandTest extends AppKernelTestCase
{
    private PurgeCommandLoggerTableCommand $command;

    private MockObject|CommandLogRepository $repository;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $this->repository = $this->createMock(CommandLogRepository::class);
        $this->command    = new PurgeCommandLoggerTableCommand(30, $this->repository);
        $application->add($this->command);

        $this->commandTester = new CommandTester($application->find('command-logger:purge'));
    }

    public function testExecuteWithDefaultThreshold(): void
    {
        $this->repository->method('purgeLogsOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $date) {
                $diff = (new \DateTimeImmutable())->diff($date);

                return 30 === $diff->days && 1 === $diff->invert;
            }))
            ->willReturn(5);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Purged 5 log entries older than 30 days.', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithCustomThreshold(): void
    {
        $this->repository->method('purgeLogsOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $date) {
                $diff = (new \DateTimeImmutable())->diff($date);

                return 10 === $diff->days && 1 === $diff->invert;
            }))
            ->willReturn(3);

        $this->commandTester->execute(['--threshold' => 10]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Purged 3 log entries older than 10 days.', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidThreshold(): void
    {
        $this->repository->expects($this->never())->method('purgeLogsOlderThan');

        $this->commandTester->execute(['--threshold' => -5]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('The threshold must be a positive integer.', $output);
        $this->assertEquals(Command::INVALID, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithNonNumericThreshold(): void
    {
        $this->repository->expects($this->never())->method('purgeLogsOlderThan');

        $this->commandTester->execute(['--threshold' => 'invalid']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('The threshold must be a positive integer.', $output);
        $this->assertEquals(Command::INVALID, $this->commandTester->getStatusCode());
    }
}
