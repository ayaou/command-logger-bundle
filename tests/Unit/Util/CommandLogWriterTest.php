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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\Util;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CommandLogWriterTest extends TestCase
{
    private MockObject|ManagerRegistry $registry;

    private MockObject|EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('command_log');
        $metadata->method('getColumnName')->willReturnArgument(0);

        $this->entityManager->method('getClassMetadata')->with(CommandLog::class)->willReturn($metadata);
    }

    /**
     * With a configured entity manager name, the writer must resolve that exact manager
     * through the registry rather than always falling back to the default one.
     */
    public function testCreateResolvesTheConfiguredNamedEntityManager(): void
    {
        $this->registry->expects($this->once())
            ->method('getManager')
            ->with('reporting')
            ->willReturn($this->entityManager);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('insert');

        $this->entityManager->method('getConnection')->willReturn($connection);

        $writer = new CommandLogWriter($this->registry, 'reporting');

        $writer->create('app:test', [], new \DateTimeImmutable(), 'token-1');
    }

    /**
     * With no configured name (the default, non-regression case), the writer must resolve
     * the default entity manager - i.e. call getManager() with no name.
     */
    public function testCreateResolvesTheDefaultEntityManagerWhenNoNameIsConfigured(): void
    {
        $this->registry->expects($this->once())
            ->method('getManager')
            ->with(null)
            ->willReturn($this->entityManager);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('insert');

        $this->entityManager->method('getConnection')->willReturn($connection);

        $writer = new CommandLogWriter($this->registry);

        $writer->create('app:test', [], new \DateTimeImmutable(), 'token-1');
    }

    public function testMarkTerminatedResolvesTheConfiguredNamedEntityManager(): void
    {
        $this->registry->expects($this->once())
            ->method('getManager')
            ->with('reporting')
            ->willReturn($this->entityManager);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('update');

        $this->entityManager->method('getConnection')->willReturn($connection);

        $writer = new CommandLogWriter($this->registry, 'reporting');

        $writer->markTerminated('token-1', new \DateTimeImmutable(), 0, 42);
    }

    public function testMarkErroredResolvesTheConfiguredNamedEntityManager(): void
    {
        $this->registry->expects($this->once())
            ->method('getManager')
            ->with('reporting')
            ->willReturn($this->entityManager);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('update');

        $this->entityManager->method('getConnection')->willReturn($connection);

        $writer = new CommandLogWriter($this->registry, 'reporting');

        $writer->markErrored('token-1', 'boom');
    }
}
