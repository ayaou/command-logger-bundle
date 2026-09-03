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
use Ayaou\CommandLoggerBundle\Repository\CommandLogStatistics;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;

class CommandLogRepositoryTest extends AppKernelTestCase
{
    private CommandLogRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(CommandLogRepository::class);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);
    }

    public function testPurgeLogsOlderThanRemovesOldLogs(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        // Insert test data
        $oldLog = new CommandLog();
        $oldLog->setCommandName('test:old')
            ->setStartTime(new \DateTimeImmutable('-40 days'))
            ->setExecutionToken('old-token');
        $newLog = new CommandLog();
        $newLog->setCommandName('test:new')
            ->setStartTime(new \DateTimeImmutable('-5 days'))
            ->setExecutionToken('new-token');

        $entityManager->persist($oldLog);
        $entityManager->persist($newLog);
        $entityManager->flush();

        // Purge logs older than 30 days
        $cutoffDate = new \DateTimeImmutable('-30 days');
        $deletedCount = $this->repository->purgeLogsOlderThan($cutoffDate);

        $this->assertEquals(1, $deletedCount);

        // Verify results
        $remainingLogs = $this->repository->findAll();
        $this->assertCount(1, $remainingLogs);
        $this->assertEquals('test:new', $remainingLogs[0]->getCommandName());
    }

    public function testPurgeLogsOlderThanNoMatchingLogs(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        // Insert test data
        $newLog = new CommandLog();
        $newLog->setCommandName('test:new')
            ->setStartTime(new \DateTimeImmutable('-5 days'))
            ->setExecutionToken('new-token');

        $entityManager->persist($newLog);
        $entityManager->flush();

        // Purge logs older than 30 days
        $cutoffDate = new \DateTimeImmutable('-30 days');
        $deletedCount = $this->repository->purgeLogsOlderThan($cutoffDate);

        $this->assertEquals(0, $deletedCount);

        // Verify results
        $remainingLogs = $this->repository->findAll();
        $this->assertCount(1, $remainingLogs);
        $this->assertEquals('test:new', $remainingLogs[0]->getCommandName());
    }

    /**
     * @deprecated coverage: CommandLogRepository::getStatistics() is a one-line delegation
     * to CommandLogStatistics, kept only so code that injects the repository directly is not
     * broken. This proves the delegation actually returns what the new class returns.
     */
    public function testGetStatisticsDelegatesToCommandLogStatistics(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $statistics = self::getContainer()->get(CommandLogStatistics::class);

        $log = new CommandLog();
        $log->setCommandName('test:delegation')
            ->setStartTime(new \DateTimeImmutable())
            ->setEndTime(new \DateTimeImmutable())
            ->setExitCode(0)
            ->setDurationMs(100)
            ->setExecutionToken('delegation-token');

        $entityManager->persist($log);
        $entityManager->flush();

        $filter = new CommandLogFilter();

        $this->assertSame($statistics->getStatistics($filter), $this->repository->getStatistics($filter));
    }

    /**
     * @deprecated coverage: same as testGetStatisticsDelegatesToCommandLogStatistics(), for
     * CommandLogRepository::getStatisticsByCommand()
     */
    public function testGetStatisticsByCommandDelegatesToCommandLogStatistics(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $statistics = self::getContainer()->get(CommandLogStatistics::class);

        $first = new CommandLog();
        $first->setCommandName('test:delegation-a')
            ->setStartTime(new \DateTimeImmutable())
            ->setEndTime(new \DateTimeImmutable())
            ->setExitCode(0)
            ->setDurationMs(100)
            ->setExecutionToken('delegation-token-a');

        $second = new CommandLog();
        $second->setCommandName('test:delegation-b')
            ->setStartTime(new \DateTimeImmutable())
            ->setEndTime(new \DateTimeImmutable())
            ->setExitCode(1)
            ->setDurationMs(200)
            ->setExecutionToken('delegation-token-b');

        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        $filter = new CommandLogFilter();

        $this->assertSame(
            $statistics->getStatisticsByCommand($filter, 10),
            $this->repository->getStatisticsByCommand($filter, 10),
        );
    }
}
