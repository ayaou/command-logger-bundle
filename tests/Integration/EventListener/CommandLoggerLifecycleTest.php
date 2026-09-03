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
use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandTerminateListener;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommand;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommandWithoutAttribute;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Ayaou\CommandLoggerBundle\Util\OutputCapture;
use Ayaou\CommandLoggerBundle\Util\SensitiveParameterRedactor;
use Ayaou\CommandLoggerBundle\Util\SupportedCommandResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Full lifecycle integration test for the command logger.
 *
 * Unlike the unit tests, this dispatches the real console.command, console.terminate and
 * console.error events through the real CommandLogger listeners, wired exactly as
 * config/services.yaml does (same event names, same handler methods), and persists through
 * a real EntityManager backed by an in-memory SQLite schema. It is the safety net for the
 * upcoming listener refactor: it must keep passing no matter how the listeners are
 * internally reorganized, as long as the observable behaviour stays the same.
 *
 * Note: Symfony\Component\Console\Tester\CommandTester cannot be used here, because
 * Command::run() never dispatches console events - only Application::doRunCommand() does.
 * The events are therefore built and dispatched by hand, exactly like a real
 * Symfony\Component\Console\Application would.
 */
class CommandLoggerLifecycleTest extends AppKernelTestCase
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

    public function testCommandStartCreatesLogWithCoreFields(): void
    {
        $dispatcher = $this->createDispatcher(true, []);

        $dispatcher->dispatch(
            new ConsoleCommandEvent(new TestCommand(), new ArrayInput([]), new BufferedOutput()),
            ConsoleEvents::COMMAND,
        );

        $log = $this->findTheOnlyLog();

        $this->assertSame('app:my-command', $log->getCommandName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getStartTime());
        $this->assertNotNull($log->getExecutionToken());
        $this->assertNotSame('', $log->getExecutionToken());
    }

    public function testCommandStartLogsArgumentsAndOptions(): void
    {
        $dispatcher = $this->createDispatcher(true, []);

        $input = new ArrayInput(
            ['name' => 'Ada', '--flag' => 'on'],
            new InputDefinition([
                new InputArgument('name', InputArgument::REQUIRED),
                new InputOption('flag', null, InputOption::VALUE_REQUIRED),
            ]),
        );

        $dispatcher->dispatch(
            new ConsoleCommandEvent(new TestCommand(), $input, new BufferedOutput()),
            ConsoleEvents::COMMAND,
        );

        $log = $this->findTheOnlyLog();

        $this->assertSame(['name' => 'Ada', 'flag' => 'on'], $log->getArguments());
    }

    public function testTerminateEventUpdatesLogCreatedByCommandEvent(): void
    {
        $dispatcher = $this->createDispatcher(true, []);

        $command = new TestCommand();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);

        $startedLog = $this->findTheOnlyLog();
        $token = $startedLog->getExecutionToken();
        $id = $startedLog->getId();

        $this->assertNull($startedLog->getEndTime());
        $this->assertNull($startedLog->getExitCode());
        $this->assertNull($startedLog->getDurationMs());

        // Force a fresh SELECT so the assertions below prove the row was really persisted,
        // not just mutated on the already-loaded object still sitting in the identity map.
        $this->entityManager->clear();

        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 0), ConsoleEvents::TERMINATE);

        $repository = $this->entityManager->getRepository(CommandLog::class);
        $terminatedLog = $repository->findOneBy(['executionToken' => $token]);

        $this->assertNotNull($terminatedLog);
        $this->assertSame($id, $terminatedLog->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $terminatedLog->getEndTime());
        $this->assertSame(0, $terminatedLog->getExitCode());
        $this->assertCount(1, $repository->findAll());

        // durationMs must never be derived from startTime/endTime: both are
        // datetime_immutable columns reloaded from the database, which only store to the
        // second, so that arithmetic would always yield a multiple of 1000ms. It is instead
        // computed from the hrtime(true) instant CommandExecutionTracker recorded in memory,
        // so it can legitimately be 0 on a very fast test run - only its absence is wrong.
        $this->assertIsInt($terminatedLog->getDurationMs());
        $this->assertGreaterThanOrEqual(0, $terminatedLog->getDurationMs());
    }

    public function testCommandWithoutTerminateEventLeavesDurationNull(): void
    {
        $dispatcher = $this->createDispatcher(true, []);

        $dispatcher->dispatch(
            new ConsoleCommandEvent(new TestCommand(), new ArrayInput([]), new BufferedOutput()),
            ConsoleEvents::COMMAND,
        );

        $log = $this->findTheOnlyLog();

        // No console.terminate was ever dispatched: this is what "unfinished" means for the
        // statistics feature (endTime IS NULL), whether the process is still running or was
        // killed before terminating cleanly. durationMs must stay null, never a guess.
        $this->assertNull($log->getEndTime());
        $this->assertNull($log->getDurationMs());
    }

    public function testErrorEventRecordsExceptionMessage(): void
    {
        $dispatcher = $this->createDispatcher(true, []);

        $command = new TestCommand();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);

        $token = $this->findTheOnlyLog()->getExecutionToken();

        $this->entityManager->clear();

        $exception = new \RuntimeException('Something went terribly wrong');
        $dispatcher->dispatch(new ConsoleErrorEvent($input, $output, $exception, $command), ConsoleEvents::ERROR);

        $log = $this->entityManager->getRepository(CommandLog::class)
            ->findOneBy(['executionToken' => $token]);

        $this->assertNotNull($log);
        $this->assertNotNull($log->getErrorMessage());
        $this->assertStringContainsString('Something went terribly wrong', $log->getErrorMessage());
    }

    public function testUnselectedCommandProducesNoLog(): void
    {
        // Neither the #[CommandLogger] attribute nor the "commands" configuration select it.
        $dispatcher = $this->createDispatcher(true, []);

        $dispatcher->dispatch(
            new ConsoleCommandEvent(new TestCommandWithoutAttribute(), new ArrayInput([]), new BufferedOutput()),
            ConsoleEvents::COMMAND,
        );

        $this->assertSame([], $this->entityManager->getRepository(CommandLog::class)->findAll());
    }

    public function testDisabledConfigurationProducesNoLog(): void
    {
        // Even an attributed command must be ignored when logging is globally disabled.
        $dispatcher = $this->createDispatcher(false, []);

        $dispatcher->dispatch(
            new ConsoleCommandEvent(new TestCommand(), new ArrayInput([]), new BufferedOutput()),
            ConsoleEvents::COMMAND,
        );

        $this->assertSame([], $this->entityManager->getRepository(CommandLog::class)->findAll());
    }

    private function findTheOnlyLog(): CommandLog
    {
        $logs = $this->entityManager->getRepository(CommandLog::class)->findAll();

        $this->assertCount(1, $logs);

        return $logs[0];
    }

    /**
     * Wires the three real listeners together exactly like config/services.yaml does
     * (same event names, same handler methods), sharing one CommandExecutionTracker so the
     * console.terminate/console.error events can find the token set by console.command.
     *
     * @param array<int|string, string> $otherCommands
     */
    private function createDispatcher(bool $enabled, array $otherCommands): EventDispatcher
    {
        $tracker = new CommandExecutionTracker();
        $writer = new CommandLogWriter($this->managerRegistry);
        $resolver = new SupportedCommandResolver($otherCommands);

        $startListener = new CommandStartListener($writer, $tracker, $enabled, $resolver, new SensitiveParameterRedactor([]), new OutputCapture());
        $terminateListener = new CommandTerminateListener($writer, $tracker, $enabled, $resolver, new OutputCapture());
        $errorListener = new CommandErrorListener($writer, $tracker, $enabled, $resolver);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$startListener, 'onConsoleCommand']);
        $dispatcher->addListener(ConsoleEvents::TERMINATE, [$terminateListener, 'onConsoleTerminate']);
        $dispatcher->addListener(ConsoleEvents::ERROR, [$errorListener, 'onConsoleError']);

        return $dispatcher;
    }
}
