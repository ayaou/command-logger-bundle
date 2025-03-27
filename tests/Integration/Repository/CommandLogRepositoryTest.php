<?php

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Repository;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CommandLogRepositoryTest extends KernelTestCase
{
    private CommandLogRepository $repository;

    /**
     * @throws ToolsException
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(CommandLogRepository::class);

        // Set up in-memory SQLite DB and create schema
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
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
}