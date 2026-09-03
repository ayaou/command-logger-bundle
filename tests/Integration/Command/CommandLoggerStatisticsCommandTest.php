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

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Command;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CommandLoggerStatisticsCommandTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CommandTester $commandTester;

    private int $tokenCounter = 0;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $application = new Application(self::$kernel);

        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);

        $this->commandTester = new CommandTester($application->find('command-logger:stats'));
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->dropSchema([$metadata]);

        $this->entityManager->close();
        parent::tearDown();
    }

    public function testDisplaysSummaryAndBreakdowns(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 1, 200);
        $this->entityManager->flush();

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('Breakdown by exit code', $output);
        $this->assertStringContainsString('Breakdown by command', $output);
        $this->assertStringContainsString('app:foo', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testFiltersByNameArgument(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:bar', 0, 100);
        $this->entityManager->flush();

        $this->commandTester->execute(['name' => 'foo']);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('app:foo', $output);
        $this->assertStringNotContainsString('app:bar', $output);
    }

    public function testFiltersByStatusOption(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:bar', 1, 100);
        $this->entityManager->flush();

        $this->commandTester->execute(['--status' => 'success']);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('app:foo', $output);
        $this->assertStringNotContainsString('app:bar', $output);
    }

    public function testRespectsLimitOptionForByCommandBreakdown(): void
    {
        $this->createLog('app:frequent', 0, 100);
        $this->createLog('app:frequent', 0, 100);
        $this->createLog('app:rare', 0, 100);
        $this->entityManager->flush();

        $this->commandTester->execute(['--limit' => 1]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('app:frequent', $output);
        $this->assertStringNotContainsString('app:rare', $output);
    }

    public function testHandlesEmptyTableGracefully(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('No exit codes recorded.', $output);
        $this->assertStringContainsString('No entries found matching the criteria.', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testRejectsStatusAndCodeTogether(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The --status and --code options cannot be used together.');

        $this->commandTester->execute(['--status' => 'success', '--code' => 0]);
    }

    public function testRejectsInvalidStatusValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The --status option must be either "success" or "error".');

        $this->commandTester->execute(['--status' => 'bogus']);
    }

    public function testRejectsNonNumericCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The --code option must be a numeric value.');

        $this->commandTester->execute(['--code' => 'abc']);
    }

    public function testRejectsInvalidFromDateFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The --from option must be formatted as "Y-m-d" or "Y-m-d H:i:s".');

        $this->commandTester->execute(['--from' => 'not-a-date']);
    }

    private function createLog(string $commandName, ?int $exitCode, ?int $durationMs): CommandLog
    {
        $start = new \DateTimeImmutable();

        $log = new CommandLog();
        $log->setCommandName($commandName)
            ->setStartTime($start)
            ->setEndTime($start)
            ->setExitCode($exitCode)
            ->setDurationMs($durationMs)
            ->setExecutionToken('token-'.++$this->tokenCounter);

        $this->entityManager->persist($log);

        return $log;
    }
}
