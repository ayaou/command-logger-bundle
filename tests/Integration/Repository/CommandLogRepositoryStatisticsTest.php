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

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Repository;

use Ayaou\CommandLoggerBundle\Dto\CommandLogFilter;
use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

class CommandLogRepositoryStatisticsTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CommandLogRepository $repository;

    private int $tokenCounter = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = self::getContainer()->get(CommandLogRepository::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);
    }

    public function testGetStatisticsCountsSuccessFailureAndUnfinished(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 0, 150);
        $this->createLog('app:foo', 1, 200);
        $this->createLog('app:foo', null, null, unfinished: true);
        $this->entityManager->flush();

        $stats = $this->repository->getStatistics(new CommandLogFilter());

        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['successCount']);
        $this->assertSame(1, $stats['failureCount']);
        $this->assertSame(1, $stats['unfinishedCount']);
    }

    public function testGetStatisticsComputesFailureRateInPhp(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 1, 100);
        $this->createLog('app:foo', 1, 100);
        $this->createLog('app:foo', 1, 100);
        $this->entityManager->flush();

        $stats = $this->repository->getStatistics(new CommandLogFilter());

        $this->assertSame(0.75, $stats['failureRate']);
    }

    public function testGetStatisticsDurationAggregatesIgnoreNullDurations(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 0, 300);
        // A "legacy" row: terminated, but logged before durationMs existed.
        $this->createLog('app:foo', 0, null);
        // Unfinished: no endTime, no durationMs either.
        $this->createLog('app:foo', null, null, unfinished: true);
        $this->entityManager->flush();

        $stats = $this->repository->getStatistics(new CommandLogFilter());

        $this->assertSame(200.0, $stats['durationMs']['avg']);
        $this->assertSame(100, $stats['durationMs']['min']);
        $this->assertSame(300, $stats['durationMs']['max']);
        $this->assertSame(2, $stats['durationMs']['count']);
    }

    public function testGetStatisticsFiltersByName(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 1, 100);
        $this->createLog('app:bar', 0, 100);
        $this->entityManager->flush();

        $stats = $this->repository->getStatistics(new CommandLogFilter(name: 'foo'));

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['successCount']);
        $this->assertSame(1, $stats['failureCount']);
    }

    public function testGetStatisticsFiltersByStatus(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 1, 100);
        $this->createLog('app:bar', 0, 100);
        $this->entityManager->flush();

        $stats = $this->repository->getStatistics(new CommandLogFilter(status: 'success'));

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['successCount']);
        $this->assertSame(0, $stats['failureCount']);
    }

    public function testGetStatisticsBreakdownByExitCode(): void
    {
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 0, 100);
        $this->createLog('app:foo', 1, 100);
        $this->createLog('app:foo', 2, 100);
        $this->createLog('app:foo', null, null, unfinished: true);
        $this->entityManager->flush();

        $stats = $this->repository->getStatistics(new CommandLogFilter());

        $this->assertSame([0 => 2, 1 => 1, 2 => 1], $stats['byExitCode']);
    }

    public function testGetStatisticsOnEmptyTableNeitherDividesByZeroNorFails(): void
    {
        $stats = $this->repository->getStatistics(new CommandLogFilter());

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['successCount']);
        $this->assertSame(0, $stats['failureCount']);
        $this->assertSame(0, $stats['unfinishedCount']);
        $this->assertSame(0.0, $stats['failureRate']);
        $this->assertNull($stats['durationMs']['avg']);
        $this->assertNull($stats['durationMs']['min']);
        $this->assertNull($stats['durationMs']['max']);
        $this->assertSame(0, $stats['durationMs']['count']);
        $this->assertSame([], $stats['byExitCode']);
    }

    public function testGetStatisticsByCommandGroupsAndRespectsLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->createLog('app:frequent', 0, 100);
        }
        for ($i = 0; $i < 3; ++$i) {
            $this->createLog('app:occasional', 0, 100);
        }
        $this->createLog('app:rare', 1, 100);
        $this->entityManager->flush();

        $byCommand = $this->repository->getStatisticsByCommand(new CommandLogFilter(), 2);

        $this->assertCount(2, $byCommand);
        $this->assertSame('app:frequent', $byCommand[0]['commandName']);
        $this->assertSame(5, $byCommand[0]['total']);
        $this->assertSame('app:occasional', $byCommand[1]['commandName']);
        $this->assertSame(3, $byCommand[1]['total']);
    }

    public function testGetStatisticsByCommandOnEmptyTableReturnsEmptyArray(): void
    {
        $byCommand = $this->repository->getStatisticsByCommand(new CommandLogFilter(), 10);

        $this->assertSame([], $byCommand);
    }

    private function createLog(
        string $commandName,
        ?int $exitCode,
        ?int $durationMs,
        bool $unfinished = false,
    ): CommandLog {
        $start = new \DateTimeImmutable();

        $log = new CommandLog();
        $log->setCommandName($commandName)
            ->setStartTime($start)
            ->setExecutionToken('token-'.++$this->tokenCounter);

        if (!$unfinished) {
            $log->setEndTime($start)
                ->setExitCode($exitCode)
                ->setDurationMs($durationMs);
        }

        $this->entityManager->persist($log);

        return $log;
    }
}
