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

namespace Ayaou\CommandLoggerBundle\EventListener\CommandLogger;

use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Ayaou\CommandLoggerBundle\Util\OutputCapture;
use Ayaou\CommandLoggerBundle\Util\SensitiveParameterRedactor;
use Ayaou\CommandLoggerBundle\Util\SupportedCommandResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
class CommandStartListener
{
    private CommandLogWriter $writer;

    private CommandExecutionTracker $commandExecutionTracker;

    private bool $enabled;

    private SupportedCommandResolver $resolver;

    private SensitiveParameterRedactor $sensitiveParameterRedactor;

    private OutputCapture $outputCapture;

    private ?LoggerInterface $logger;

    public function __construct(
        CommandLogWriter $writer,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
        SupportedCommandResolver $resolver,
        SensitiveParameterRedactor $sensitiveParameterRedactor,
        OutputCapture $outputCapture,
        ?LoggerInterface $logger = null,
    ) {
        $this->writer = $writer;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled = $enabled;
        $this->resolver = $resolver;
        $this->sensitiveParameterRedactor = $sensitiveParameterRedactor;
        $this->outputCapture = $outputCapture;
        $this->logger = $logger;
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$this->enabled || !$command || !$this->resolver->supports($command)) {
            return;
        }

        $commandName = $command->getName();
        if (!$commandName) {
            return;
        }

        $input = $event->getInput();
        $executionToken = Uuid::v7()->toRfc4122();

        $this->commandExecutionTracker->setToken($command, $executionToken);
        $this->commandExecutionTracker->setStartTimestamp($command, hrtime(true));

        // A no-op unless command_logger.output_capture.enabled is true. Deliberately outside
        // the try/catch below: OutputCapture::start() swallows its own failures, because a
        // throw here would reach the command instead of the logging code that caused it.
        $this->outputCapture->start($event->getOutput());

        // SensitiveParameterRedactor::redact() keeps a wider array<int|string, mixed> signature
        // to stay reusable, but Console argument and option names are always strings - narrow
        // the type back down here for the CommandLogWriter::create() call below.
        /** @var array<string, mixed> $arguments */
        $arguments = $this->sensitiveParameterRedactor->redact($input->getArguments() + $input->getOptions());

        try {
            $this->writer->create($commandName, $arguments, new \DateTimeImmutable(), $executionToken);
        } catch (\Throwable $exception) {
            // A logging failure must never take the user's command down with it: give up on
            // writing this entry and let the command run its course.
            $this->logger?->error(
                sprintf('Command logger bundle failed to log the start of command "%s".', $commandName),
                ['command' => $commandName, 'exception' => $exception],
            );
        }
    }
}
