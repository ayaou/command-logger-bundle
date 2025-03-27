<?php

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Repository;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;

class CommandLogRepositoryTest extends AppKernelTestCase
{
    private CommandLogRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(CommandLogRepository::class);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool    = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata      = $entityManager->getClassMetadata(CommandLog::class);
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
        $cutoffDate   = new \DateTimeImmutable('-30 days');
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
        $cutoffDate   = new \DateTimeImmutable('-30 days');
        $deletedCount = $this->repository->purgeLogsOlderThan($cutoffDate);

        $this->assertEquals(0, $deletedCount);

        // Verify results
        $remainingLogs = $this->repository->findAll();
        $this->assertCount(1, $remainingLogs);
        $this->assertEquals('test:new', $remainingLogs[0]->getCommandName());
    }
}
