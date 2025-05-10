<?php

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Command;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ShowCommandLoggerEntriesCommandTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CommandLogRepository $repository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        // Boot the kernel with the test environment
        self::bootKernel(['environment' => 'test']);
        $application = new Application(self::$kernel);

        // Get the entity manager and repository
        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = $this->entityManager->getRepository(CommandLog::class);

        // Create the database schema
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);

        // Find the command by name
        $this->commandTester = new CommandTester($application->find('command-logger:show'));
    }

    protected function tearDown(): void
    {
        // Drop the schema to reset the in-memory database
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->dropSchema([$metadata]);

        $this->entityManager->close();
        parent::tearDown();
    }

    public function testExecuteDefaultListView(): void
    {
        // Create test data
        $entry1 = $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $entry2 = $this->createCommandLog(2, 'other:command', 1, '550e8400-e29b-41d4-a716-446655440001');
        $this->entityManager->flush();

        // Execute the command with simulated input to exit pagination
        $this->commandTester->setInputs(['q']);
        $this->commandTester->execute([]);

        // Verify the command fetched the correct entries
        $entries = $this->repository->findBy([], ['startTime' => 'DESC'], 10);
        $this->assertCount(2, $entries);
        $this->assertSame($entry2->getId(), $entries[0]->getId());
        $this->assertSame($entry1->getId(), $entries[1]->getId());

        // Verify minimal output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Status   ID   Command         Start Time            End Time              Exit Code   Execution Token', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteListViewWithNameFilter(): void
    {
        // Create test data
        $entry1 = $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $this->createCommandLog(2, 'other:command', 1, '550e8400-e29b-41d4-a716-446655440001');
        $this->entityManager->flush();

        // Execute the command with name filter
        $this->commandTester->setInputs(['q']);
        $this->commandTester->execute(['name' => 'test:command']);

        // Verify the command fetched the correct entries
        $entries = $this->repository->findBy(['commandName' => 'test:command'], ['startTime' => 'DESC'], 10);
        $this->assertCount(1, $entries);
        $this->assertSame($entry1->getId(), $entries[0]->getId());

        // Verify minimal output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('test:command', $output);
        $this->assertStringNotContainsString('other:command', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteListViewWithSuccessFilter(): void
    {
        // Create test data
        $entry1 = $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $this->createCommandLog(2, 'other:command', 1, '550e8400-e29b-41d4-a716-446655440001');
        $this->entityManager->flush();

        // Execute the command with --success
        $this->commandTester->setInputs(['q']);
        $this->commandTester->execute(['--success' => true]);

        // Verify the command fetched the correct entries
        $entries = $this->repository->findBy(['exitCode' => 0], ['startTime' => 'DESC'], 10);
        $this->assertCount(1, $entries);
        $this->assertSame($entry1->getId(), $entries[0]->getId());

        // Verify minimal output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('test:command', $output);
        $this->assertStringNotContainsString('other:command', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteListViewWithErrorFilter(): void
    {
        // Create test data
        $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $entry2 = $this->createCommandLog(2, 'other:command', 1, '550e8400-e29b-41d4-a716-446655440001');
        $this->entityManager->flush();

        // Execute the command with --error
        $this->commandTester->setInputs(['q']);
        $this->commandTester->execute(['--error' => true]);

        // Verify the command fetched the correct entries
        $entries = $this->repository->createQueryBuilder('cl')
            ->where('cl.exitCode != :exitCode')
            ->setParameter('exitCode', 0)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        $this->assertCount(1, $entries);
        $this->assertSame($entry2->getId(), $entries[0]->getId());

        // Verify minimal output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('other:command', $output);
        $this->assertStringNotContainsString('test:command', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteListViewWithCodeFilter(): void
    {
        // Create test data
        $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $entry2 = $this->createCommandLog(2, 'other:command', 1, '550e8400-e29b-41d4-a716-446655440001');
        $this->entityManager->flush();

        // Execute the command with --code=1
        $this->commandTester->setInputs(['q']);
        $this->commandTester->execute(['--code' => 1]);

        // Verify the command fetched the correct entries
        $entries = $this->repository->findBy(['exitCode' => 1], ['startTime' => 'DESC'], 10);
        $this->assertCount(1, $entries);
        $this->assertSame($entry2->getId(), $entries[0]->getId());

        // Verify minimal output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('other:command', $output);
        $this->assertStringNotContainsString('test:command', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteSingleEntryView(): void
    {
        // Create test data
        $entry = $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $this->entityManager->flush();

        // Execute the command with --id=1
        $this->commandTester->execute(['--id' => 1]);

        // Verify the command fetched the correct entry
        $fetchedEntry = $this->repository->find(1);
        $this->assertNotNull($fetchedEntry);
        $this->assertSame($entry->getId(), $fetchedEntry->getId());
        $this->assertSame('test:command', $fetchedEntry->getCommandName());
        $this->assertSame(['option' => 'value'], $fetchedEntry->getArguments());
        $this->assertEquals('2025-05-10 10:00:00', $fetchedEntry->getStartTime()->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-05-10 10:01:00', $fetchedEntry->getEndTime()->format('Y-m-d H:i:s'));
        $this->assertSame(0, $fetchedEntry->getExitCode());
        $this->assertNull($fetchedEntry->getErrorMessage());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $fetchedEntry->getExecutionToken());

        // Verify minimal output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Command: test:command', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteSingleEntryViewWithInvalidId(): void
    {
        // No data created
        $this->entityManager->flush();

        // Execute the command with --id=999
        $this->commandTester->execute(['--id' => 999]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No entry found with ID: 999', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithNoEntries(): void
    {
        // No data created
        $this->entityManager->flush();

        // Execute the command
        $this->commandTester->execute([]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No entries found matching the criteria.', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithErrorAndSuccessFlagsTogether(): void
    {
        // Create test data
        $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $this->entityManager->flush();

        // Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The --error and --success options cannot be used together.');

        // Execute the command with --error and --success
        $this->commandTester->execute(['--error' => true, '--success' => true]);
    }

    public function testExecuteWithErrorAndCodeOption(): void
    {
        // Create test data
        $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $this->entityManager->flush();

        // Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The --error or --success options cannot be used with the --code option.');

        // Execute the command with --error and --code
        $this->commandTester->execute(['--error' => true, '--code' => 0]);
    }

    public function testExecuteWithIdAndOtherOptions(): void
    {
        // Create test data
        $this->createCommandLog(1, 'test:command', 0, '550e8400-e29b-41d4-a716-446655440000');
        $this->entityManager->flush();

        // Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('When ID is specified, no other options or arguments are allowed, except the default limit.');

        // Execute the command with --id and --limit
        $this->commandTester->execute(['--id' => 1, '--limit' => 5]);
    }

    private function createCommandLog(int $id, string $commandName, ?int $exitCode, string $executionToken): CommandLog
    {
        $entry = new CommandLog();
        $entry->setCommandName($commandName)
            ->setArguments(['option' => 'value'])
            ->setStartTime(new \DateTimeImmutable('2025-05-10 10:00:00'))
            ->setEndTime(new \DateTimeImmutable('2025-05-10 10:01:00'))
            ->setExitCode($exitCode)
            ->setErrorMessage(null)
            ->setExecutionToken($executionToken);

        // Set the ID explicitly to match expected values
        $reflection = new \ReflectionClass($entry);
        $property = $reflection->getProperty('id');
         $property->setValue($entry, $id);

        $this->entityManager->persist($entry);
        return $entry;
    }
}