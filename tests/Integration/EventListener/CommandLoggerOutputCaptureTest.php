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
use Ayaou\CommandLoggerBundle\EventListener\CommandLogger\CommandTerminateListener;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommand;
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
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * End to end coverage of the opt-in output capture, from console.command through to the
 * stored row - answering ayaou/command-logger-bundle#20.
 *
 * A StreamOutput over php://temp is used rather than the BufferedOutput the sibling
 * lifecycle test relies on: capture works by filtering the stream an output writes to, so
 * an output with no stream behind it is precisely the case where nothing is captured.
 */
class CommandLoggerOutputCaptureTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ManagerRegistry $managerRegistry;

    /**
     * @var resource
     */
    private $stream;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->managerRegistry = self::getContainer()->get('doctrine');

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);

        $this->stream = fopen('php://temp', 'r+');
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }

        parent::tearDown();
    }

    public function testCapturedOutputIsPersistedWhenTheFeatureIsEnabled(): void
    {
        $log = $this->runCommandPrinting(['[OK] 3 products loaded'], new OutputCapture(true, 4096));

        $this->assertSame("[OK] 3 products loaded\n", $log->getOutput());
    }

    public function testOutputStaysNullWhenTheFeatureIsDisabled(): void
    {
        $log = $this->runCommandPrinting(['[OK] 3 products loaded'], new OutputCapture(false, 4096));

        $this->assertNull($log->getOutput());
        // The row is otherwise complete: only the output is withheld.
        $this->assertSame(0, $log->getExitCode());
        $this->assertNotNull($log->getEndTime());
    }

    public function testOutputStaysNullWhenTheCommandPrintedNothing(): void
    {
        $log = $this->runCommandPrinting([], new OutputCapture(true, 4096));

        $this->assertNull($log->getOutput());
    }

    public function testSeveralLinesArePersistedInOrder(): void
    {
        $log = $this->runCommandPrinting(
            ['Importing products', 'Imported 3 of 3', '[OK] done'],
            new OutputCapture(true, 4096),
        );

        $this->assertSame("Importing products\nImported 3 of 3\n[OK] done\n", $log->getOutput());
    }

    public function testOverlongOutputIsPersistedTruncatedAndSuffixed(): void
    {
        $log = $this->runCommandPrinting([str_repeat('y', 5000)], new OutputCapture(true, 200));

        $output = $log->getOutput();

        $this->assertNotNull($output);
        $this->assertStringEndsWith(' [truncated]', $output);
        $this->assertLessThan(5000, \strlen($output));
    }

    /**
     * The promise this feature must not break: the observed command still prints exactly
     * what it would have printed with the bundle absent.
     */
    public function testTheObservedCommandStillPrintsEverythingItWouldHave(): void
    {
        $this->runCommandPrinting(['first', 'second', 'third'], new OutputCapture(true, 4096));

        rewind($this->stream);

        $this->assertSame("first\nsecond\nthird\n", stream_get_contents($this->stream));
    }

    /**
     * @param array<int, string> $lines
     */
    private function runCommandPrinting(array $lines, OutputCapture $capture): CommandLog
    {
        $tracker = new CommandExecutionTracker();
        $writer = new CommandLogWriter($this->managerRegistry);
        $resolver = new SupportedCommandResolver([]);

        $startListener = new CommandStartListener($writer, $tracker, true, $resolver, new SensitiveParameterRedactor([]), $capture);
        $terminateListener = new CommandTerminateListener($writer, $tracker, true, $resolver, $capture);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$startListener, 'onConsoleCommand']);
        $dispatcher->addListener(ConsoleEvents::TERMINATE, [$terminateListener, 'onConsoleTerminate']);

        $command = new TestCommand();
        $input = new ArrayInput([]);
        $output = new StreamOutput($this->stream, StreamOutput::VERBOSITY_NORMAL, false);

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);

        foreach ($lines as $line) {
            $output->writeln($line);
        }

        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 0), ConsoleEvents::TERMINATE);

        // The listeners write through DBAL, behind the ORM's back: the identity map has to be
        // dropped or findAll() would hand back the stale entity read before the UPDATE.
        $this->entityManager->clear();

        $logs = $this->entityManager->getRepository(CommandLog::class)->findAll();
        $this->assertCount(1, $logs);

        return $logs[0];
    }
}
