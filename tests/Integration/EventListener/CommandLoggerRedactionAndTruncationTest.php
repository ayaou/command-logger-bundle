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
use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandErrorListener;
use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandStartListener;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommand;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Ayaou\CommandLoggerBundle\Util\SensitiveParameterRedactor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Proves, at the persistence level, that sensitive command parameters are redacted
 * before hitting the `command_log` table and that error messages are bounded to a
 * configured byte length before hitting the `text` column.
 *
 * Kept as a separate file from CommandLoggerLifecycleTest, which is a pinned safety net
 * for the pre-existing listener behaviour and must not be modified by this change.
 */
class CommandLoggerRedactionAndTruncationTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ManagerRegistry $managerRegistry;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->managerRegistry = self::getContainer()->get('doctrine');

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);
    }

    public function testPasswordOptionIsRedactedInPersistedLog(): void
    {
        $log = $this->dispatchCommandStart(
            ['password', 'token'],
            ['password' => 'hunter2', 'username' => 'ada'],
        );

        $this->assertSame('[REDACTED]', $log->getArguments()['password']);
        $this->assertSame('ada', $log->getArguments()['username']);
    }

    public function testDbPasswordOptionIsRedactedAsSubstringMatch(): void
    {
        $log = $this->dispatchCommandStart(
            ['password'],
            ['db-password' => 'hunter2'],
        );

        $this->assertSame('[REDACTED]', $log->getArguments()['db-password']);
    }

    public function testUppercasePasswordOptionIsRedactedCaseInsensitively(): void
    {
        $log = $this->dispatchCommandStart(
            ['password'],
            ['PASSWORD' => 'hunter2'],
        );

        $this->assertSame('[REDACTED]', $log->getArguments()['PASSWORD']);
    }

    public function testEmptySensitiveParametersConfigDisablesRedactionInPersistedLog(): void
    {
        $log = $this->dispatchCommandStart(
            [],
            ['password' => 'hunter2'],
        );

        $this->assertSame('hunter2', $log->getArguments()['password']);
    }

    public function testLongErrorMessageIsTruncatedAndSuffixedInPersistedLog(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = new TestCommand();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $dispatcher = new EventDispatcher();
        $writer = new CommandLogWriter($this->managerRegistry);
        $startListener = new CommandStartListener($writer, $tracker, true, [], new SensitiveParameterRedactor([]));
        $errorListener = new CommandErrorListener($writer, $tracker, true, [], 100);
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$startListener, 'onConsoleCommand']);
        $dispatcher->addListener(ConsoleEvents::ERROR, [$errorListener, 'onConsoleError']);

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);

        $error = new \Exception(str_repeat('x', 1000));
        $dispatcher->dispatch(new ConsoleErrorEvent($input, $output, $error, $command), ConsoleEvents::ERROR);

        $this->entityManager->clear();
        $log = $this->entityManager->getRepository(CommandLog::class)->findAll()[0];

        $this->assertLessThanOrEqual(100, \strlen((string) $log->getErrorMessage()));
        $this->assertStringEndsWith(' [truncated]', (string) $log->getErrorMessage());
    }

    public function testShortErrorMessageIsPersistedUnchanged(): void
    {
        $tracker = new CommandExecutionTracker();
        $command = new TestCommand();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $dispatcher = new EventDispatcher();
        $writer = new CommandLogWriter($this->managerRegistry);
        $startListener = new CommandStartListener($writer, $tracker, true, [], new SensitiveParameterRedactor([]));
        $errorListener = new CommandErrorListener($writer, $tracker, true, [], 65535);
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$startListener, 'onConsoleCommand']);
        $dispatcher->addListener(ConsoleEvents::ERROR, [$errorListener, 'onConsoleError']);

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);

        $error = new \Exception('Short error');
        $dispatcher->dispatch(new ConsoleErrorEvent($input, $output, $error, $command), ConsoleEvents::ERROR);

        $this->entityManager->clear();
        $log = $this->entityManager->getRepository(CommandLog::class)->findAll()[0];

        $this->assertStringContainsString('Short error', (string) $log->getErrorMessage());
        $this->assertStringNotContainsString('[truncated]', (string) $log->getErrorMessage());
    }

    /**
     * @param array<int, string>    $sensitiveParameters
     * @param array<string, string> $options
     */
    private function dispatchCommandStart(array $sensitiveParameters, array $options): CommandLog
    {
        $tracker = new CommandExecutionTracker();
        $redactor = new SensitiveParameterRedactor($sensitiveParameters);
        $startListener = new CommandStartListener(new CommandLogWriter($this->managerRegistry), $tracker, true, [], $redactor);

        $definition = new InputDefinition(array_map(
            static fn (string $name) => new InputOption($name, null, InputOption::VALUE_REQUIRED),
            array_keys($options),
        ));
        $input = new ArrayInput(array_combine(
            array_map(static fn (string $name) => '--'.$name, array_keys($options)),
            array_values($options),
        ), $definition);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$startListener, 'onConsoleCommand']);

        $dispatcher->dispatch(new ConsoleCommandEvent(new TestCommand(), $input, new BufferedOutput()), ConsoleEvents::COMMAND);

        $logs = $this->entityManager->getRepository(CommandLog::class)->findAll();
        $this->assertCount(1, $logs);

        return $logs[0];
    }
}
