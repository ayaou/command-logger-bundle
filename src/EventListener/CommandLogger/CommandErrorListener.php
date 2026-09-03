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

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class CommandErrorListener extends AbstractCommandListener
{
    private const TRUNCATION_SUFFIX = ' [truncated]';

    private EntityManagerInterface $entityManager;

    private CommandExecutionTracker $commandExecutionTracker;

    private bool $enabled;

    /**
     * @var array<int|string, string>
     */
    private array $otherCommands;

    private int $maxErrorMessageLength;

    /**
     * @var array<int, string>
     */
    private array $attributedCommands;

    private ?LoggerInterface $logger;

    /**
     * @param array<int|string, string> $otherCommands
     * @param int                       $maxErrorMessageLength Maximum byte length of the stored error message
     * @param array<int, string>        $attributedCommands    names (and aliases) collected at
     *                                                         compile time by CommandLoggerPass
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CommandExecutionTracker $commandExecutionTracker,
        bool $enabled,
        array $otherCommands = [],
        int $maxErrorMessageLength = 65535,
        array $attributedCommands = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->entityManager = $entityManager;
        $this->commandExecutionTracker = $commandExecutionTracker;
        $this->enabled = $enabled;
        $this->otherCommands = $otherCommands;
        $this->maxErrorMessageLength = $maxErrorMessageLength;
        $this->attributedCommands = $attributedCommands;
        $this->logger = $logger;
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->enabled || !$command || !$this->isSupportedCommand($command, $this->otherCommands, $this->attributedCommands)) {
            return;
        }

        $executionToken = $this->commandExecutionTracker->getToken($command);
        if (!$executionToken) {
            return;
        }

        try {
            $log = $this->entityManager->getRepository(CommandLog::class)
                ->findOneBy(['executionToken' => $executionToken]);

            if ($log) {
                $errorDetails = $this->getErrorDetails($event->getError());
                $log->setErrorMessage($this->truncate(implode("\n\n\n", $errorDetails)));

                $this->entityManager->persist($log);
                $this->entityManager->flush();
            }
        } catch (\Throwable $exception) {
            // This runs while a business exception is being handled: never touch $event or the
            // exception it carries here - only the write to the log table is given up on. The
            // original exception must reach the user exactly as it would have without this bundle.
            $this->logger?->error(
                sprintf('Command logger bundle failed to log the error of command "%s".', $command->getName()),
                ['command' => $command->getName(), 'exception' => $exception],
            );
        }
    }

    /**
     * Bounds the error message to the configured byte length, so it always fits the
     * `text` column regardless of how many chained exception traces were concatenated.
     * Cuts on a byte boundary with mb_strcut so a multi-byte character is never split,
     * then appends the truncation suffix while staying within the overall limit.
     */
    private function truncate(string $message): string
    {
        if (\strlen($message) <= $this->maxErrorMessageLength) {
            return $message;
        }

        $limit = max(0, $this->maxErrorMessageLength - \strlen(self::TRUNCATION_SUFFIX));

        return mb_strcut($message, 0, $limit).self::TRUNCATION_SUFFIX;
    }

    /**
     * @return string[]
     */
    private function getErrorDetails(\Throwable $error): array
    {
        $errorDetails = [$error->getMessage()."\n".$error->getTraceAsString()];

        $limit = 10;
        $previous = $error->getPrevious();
        while ($previous && $limit-- > 0) {
            $errorDetails[] = $previous->getMessage()."\n".$previous->getTraceAsString();
            $previous = $previous->getPrevious();
        }

        return $errorDetails;
    }
}
