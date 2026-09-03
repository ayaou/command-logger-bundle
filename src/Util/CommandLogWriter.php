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

namespace Ayaou\CommandLoggerBundle\Util;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Writes CommandLog rows through DBAL rather than the ORM's unit of work.
 *
 * The three listeners in EventListener/CommandLogger observe every console command that
 * runs in the application, including ones that manage their own ORM unit of work (e.g. an
 * import command that persist()s hundreds of entities without ever flushing, on purpose, in
 * a dry run). Writing the log entry with EntityManagerInterface::persist()/flush() would
 * flush that entire unit of work along with it - an observability tool must never cause
 * side effects on what it observes. Going through Connection::insert()/update() directly
 * bypasses the unit of work entirely, so the command's own pending changes are left exactly
 * as the command left them.
 *
 * Table and column names are always read from CommandLog's own class metadata rather than
 * hard-coded, so this class cannot drift from the entity mapping it writes for.
 *
 * @internal
 */
class CommandLogWriter
{
    private ManagerRegistry $managerRegistry;

    /**
     * Name of the Doctrine entity manager to write to, as configured through
     * "command_logger.entity_manager". Null resolves to the default entity manager -
     * existing behavior, unchanged.
     */
    private ?string $entityManagerName;

    public function __construct(ManagerRegistry $managerRegistry, ?string $entityManagerName = null)
    {
        $this->managerRegistry = $managerRegistry;
        $this->entityManagerName = $entityManagerName;
    }

    /**
     * Resolved on every call rather than cached, exactly like ServiceEntityRepository does -
     * the registry may hand back a different (or reopened) manager instance across the
     * lifetime of a long-running process.
     */
    private function getEntityManager(): EntityManagerInterface
    {
        $manager = $this->managerRegistry->getManager($this->entityManagerName);

        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('The Doctrine manager "%s" is not an ORM entity manager (got an instance of "%s").', $this->entityManagerName ?? 'default', $manager::class));
        }

        return $manager;
    }

    /**
     * Inserts the row for a command that has just started.
     *
     * @param array<string, mixed> $arguments
     */
    public function create(
        string $commandName,
        array $arguments,
        \DateTimeImmutable $startTime,
        string $executionToken,
    ): void {
        $entityManager = $this->getEntityManager();
        $metadata = $entityManager->getClassMetadata(CommandLog::class);

        $entityManager->getConnection()->insert(
            $metadata->getTableName(),
            [
                $metadata->getColumnName('commandName') => $commandName,
                $metadata->getColumnName('arguments') => $arguments,
                $metadata->getColumnName('startTime') => $startTime,
                $metadata->getColumnName('executionToken') => $executionToken,
            ],
            [
                $metadata->getColumnName('commandName') => Types::STRING,
                $metadata->getColumnName('arguments') => Types::JSON,
                $metadata->getColumnName('startTime') => Types::DATETIME_IMMUTABLE,
                $metadata->getColumnName('executionToken') => Types::STRING,
            ],
        );
    }

    /**
     * Updates the row matching $executionToken with the outcome of the command, in a single
     * UPDATE - no SELECT is issued beforehand. A token with no matching row (e.g. the start
     * write itself previously failed) simply updates zero rows.
     */
    public function markTerminated(
        string $executionToken,
        \DateTimeImmutable $endTime,
        int $exitCode,
        ?int $durationMs,
    ): void {
        $entityManager = $this->getEntityManager();
        $metadata = $entityManager->getClassMetadata(CommandLog::class);

        $entityManager->getConnection()->update(
            $metadata->getTableName(),
            [
                $metadata->getColumnName('endTime') => $endTime,
                $metadata->getColumnName('exitCode') => $exitCode,
                $metadata->getColumnName('durationMs') => $durationMs,
            ],
            [
                $metadata->getColumnName('executionToken') => $executionToken,
            ],
            [
                $metadata->getColumnName('endTime') => Types::DATETIME_IMMUTABLE,
                $metadata->getColumnName('exitCode') => Types::INTEGER,
                $metadata->getColumnName('durationMs') => Types::INTEGER,
                $metadata->getColumnName('executionToken') => Types::STRING,
            ],
        );
    }

    /**
     * Updates the row matching $executionToken with the error message, in a single UPDATE -
     * no SELECT is issued beforehand. A token with no matching row simply updates zero rows.
     */
    public function markErrored(string $executionToken, string $errorMessage): void
    {
        $entityManager = $this->getEntityManager();
        $metadata = $entityManager->getClassMetadata(CommandLog::class);

        $entityManager->getConnection()->update(
            $metadata->getTableName(),
            [
                $metadata->getColumnName('errorMessage') => $errorMessage,
            ],
            [
                $metadata->getColumnName('executionToken') => $executionToken,
            ],
            [
                $metadata->getColumnName('errorMessage') => Types::TEXT,
                $metadata->getColumnName('executionToken') => Types::STRING,
            ],
        );
    }
}
