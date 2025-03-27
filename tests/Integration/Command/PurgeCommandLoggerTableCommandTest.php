<?php

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Command;

use Ayaou\CommandLoggerBundle\Command\PurgeCommandLoggerTableCommand;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeCommandLoggerTableCommandTest extends KernelTestCase
{
    private PurgeCommandLoggerTableCommand $command;
    private MockObject|CommandLogRepository $repository;
    private CommandTester $commandTester;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $this->repository = $this->createMock(CommandLogRepository::class);
        $this->command = new PurgeCommandLoggerTableCommand(30, $this->repository);
        $application->add($this->command);

        $this->commandTester = new CommandTester($application->find('command-logger:purge'));
    }

    public function testExecuteWithDefaultThreshold(): void
    {
        $this->repository->method('purgeLogsOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $date) {
                $diff = (new \DateTimeImmutable())->diff($date);
                return $diff->days === 30 && $diff->invert === 1;
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
                return $diff->days === 10 && $diff->invert === 1;
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