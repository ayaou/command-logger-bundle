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

namespace Ayaou\CommandLoggerBundle\Tests\Integration\EventListener;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandStartListener;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommand;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Ayaou\CommandLoggerBundle\Util\SensitiveParameterRedactor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Proves the defect this refactor exists to fix: EntityManagerInterface::flush() with no
 * argument flushes the *entire* unit of work, not just the row the listener itself wants to
 * write - and, since Doctrine ORM 3, flush($entity) to scope it to one entity no longer
 * exists at all. A command that persist()s entities on purpose without flushing them (e.g. a
 * dry-run import inspecting 500 rows) must not see them written to the database just because
 * the command happened to be observed by this bundle - an observability tool must not modify
 * what it observes.
 *
 * Kept as a separate file from CommandLoggerLifecycleTest, which is a pinned safety net for
 * the pre-existing listener behaviour and must not be modified by this change.
 */
class CommandLoggerUnitOfWorkIsolationTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);
    }

    public function testCallerUnflushedEntityIsNeverWrittenByTheStartListener(): void
    {
        $dangling = $this->persistDanglingLogWithoutFlushing('dangling-token-should-never-reach-the-database');

        $this->createDispatcher()->dispatch(
            new ConsoleCommandEvent(new TestCommand(), new ArrayInput([]), new BufferedOutput()),
            ConsoleEvents::COMMAND,
        );

        // findAll() issues a real SQL SELECT against the database - it can only return rows
        // that were actually written. With the old persist()+flush() implementation, the
        // listener's own flush() call flushed $dangling right along with its own row, because
        // flush() with no argument flushes the whole unit of work: this would return 2 rows.
        $rows = $this->entityManager->getRepository(CommandLog::class)->findAll();

        $this->assertCount(1, $rows, 'Only the row written by the listener itself must reach the database.');
        $this->assertSame('app:my-command', $rows[0]->getCommandName());
        $this->assertNotSame('dangling-token-should-never-reach-the-database', $rows[0]->getExecutionToken());

        // $dangling was never flushed: it must still have no identity.
        $this->assertNull($dangling->getId());
    }

    public function testCallerUnitOfWorkStaysPendingAfterTheListenerRuns(): void
    {
        $dangling = $this->persistDanglingLogWithoutFlushing('dangling-token-should-stay-pending');

        $this->createDispatcher()->dispatch(
            new ConsoleCommandEvent(new TestCommand(), new ArrayInput([]), new BufferedOutput()),
            ConsoleEvents::COMMAND,
        );

        // $dangling must still be a scheduled insertion in the caller's own unit of work: the
        // listener must not have touched it, in either direction.
        $scheduledInsertions = $this->entityManager->getUnitOfWork()->getScheduledEntityInsertions();

        $this->assertContains($dangling, $scheduledInsertions, 'The caller\'s pending entity must still be scheduled for insertion.');
        $this->assertNull($dangling->getId(), 'The caller\'s pending entity must still be unflushed - it has no identity yet.');
    }

    private function persistDanglingLogWithoutFlushing(string $executionToken): CommandLog
    {
        $dangling = new CommandLog();
        $dangling->setCommandName('app:dry-run-import')
            ->setStartTime(new \DateTimeImmutable())
            ->setExecutionToken($executionToken);

        // Simulates a command that manages its own unit of work and persist()s an entity on
        // purpose without flushing it - e.g. a dry run - before console.command is dispatched.
        $this->entityManager->persist($dangling);

        return $dangling;
    }

    private function createDispatcher(): EventDispatcher
    {
        $tracker = new CommandExecutionTracker();
        $startListener = new CommandStartListener(new CommandLogWriter($this->entityManager), $tracker, true, [], new SensitiveParameterRedactor([]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$startListener, 'onConsoleCommand']);

        return $dispatcher;
    }
}
