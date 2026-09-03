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

namespace Ayaou\CommandLoggerBundle\Repository;

use Ayaou\CommandLoggerBundle\Dto\CommandLogFilter;

/**
 * Aggregate statistics over command_log rows: volume by outcome, failure rate, duration
 * extrema, and the same metrics grouped by command name.
 *
 * Split out of CommandLogRepository because this half carries a constraint the other half
 * (finding, paginating, purging) does not: portability across MySQL, PostgreSQL and SQLite,
 * the three database engines this bundle targets. Every query here is deliberately built
 * from nothing but COUNT/AVG/MIN/MAX and GROUP BY - no date function, no percentile - so it
 * runs unchanged on all three.
 *
 * Depends on CommandLogRepository rather than ManagerRegistry so entity manager resolution
 * (which may be the one configured by command_logger.entity_manager) stays in a single
 * place, and so every query here goes through
 * CommandLogRepository::createFilteredQueryBuilder() - the same filtering point
 * getPaginatedResults() uses, guaranteeing statistics are computed over exactly the rows the
 * list endpoint would show for the same filter.
 *
 * @internal
 */
class CommandLogStatistics
{
    public function __construct(
        private readonly CommandLogRepository $repository,
    ) {
    }

    /**
     * The failure rate is computed here in PHP rather than in SQL, sidestepping the
     * division-by-zero (and cross-engine differences handling it) that a SQL-side ratio
     * would risk on an empty result set.
     *
     * @return array{
     *     total: int,
     *     successCount: int,
     *     failureCount: int,
     *     unfinishedCount: int,
     *     failureRate: float,
     *     durationMs: array{avg: float|null, min: int|null, max: int|null, count: int},
     *     byExitCode: array<int, int>,
     * }
     */
    public function getStatistics(CommandLogFilter $filter): array
    {
        $total = $this->countMatching($filter);
        $successCount = $this->countMatching($filter, 'cl.exitCode = 0');
        $failureCount = $this->countMatching($filter, 'cl.exitCode != 0');
        $unfinishedCount = $this->countMatching($filter, 'cl.endTime IS NULL');

        return [
            'total' => $total,
            'successCount' => $successCount,
            'failureCount' => $failureCount,
            'unfinishedCount' => $unfinishedCount,
            'failureRate' => $total > 0 ? $failureCount / $total : 0.0,
            'durationMs' => $this->durationStatistics($filter),
            'byExitCode' => $this->countByExitCode($filter),
        ];
    }

    /**
     * The same metrics as getStatistics(), grouped by commandName, sorted by volume
     * (descending) and bounded to $limit entries.
     *
     * @return array<int, array{
     *     commandName: string,
     *     total: int,
     *     successCount: int,
     *     failureCount: int,
     *     unfinishedCount: int,
     *     failureRate: float,
     *     durationMs: array{avg: float|null, min: int|null, max: int|null, count: int},
     * }>
     */
    public function getStatisticsByCommand(CommandLogFilter $filter, int $limit): array
    {
        $totals = $this->countByCommand($filter);
        $successCounts = $this->countByCommand($filter, 'cl.exitCode = 0');
        $failureCounts = $this->countByCommand($filter, 'cl.exitCode != 0');
        $unfinishedCounts = $this->countByCommand($filter, 'cl.endTime IS NULL');
        $durationStats = $this->durationStatisticsByCommand($filter);

        $emptyDuration = ['avg' => null, 'min' => null, 'max' => null, 'count' => 0];

        $stats = [];
        foreach ($totals as $commandName => $total) {
            $failureCount = $failureCounts[$commandName] ?? 0;

            $stats[] = [
                'commandName' => $commandName,
                'total' => $total,
                'successCount' => $successCounts[$commandName] ?? 0,
                'failureCount' => $failureCount,
                'unfinishedCount' => $unfinishedCounts[$commandName] ?? 0,
                'failureRate' => $total > 0 ? $failureCount / $total : 0.0,
                'durationMs' => $durationStats[$commandName] ?? $emptyDuration,
            ];
        }

        usort($stats, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return array_slice($stats, 0, $limit);
    }

    private function countMatching(CommandLogFilter $filter, ?string $extraCondition = null): int
    {
        $qb = $this->repository->createFilteredQueryBuilder($filter)->select('COUNT(cl.id)');

        if (null !== $extraCondition) {
            $qb->andWhere($extraCondition);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array{avg: float|null, min: int|null, max: int|null, count: int}
     */
    private function durationStatistics(CommandLogFilter $filter): array
    {
        $qb = $this->repository->createFilteredQueryBuilder($filter)
            ->select('AVG(cl.durationMs) AS avgDuration, MIN(cl.durationMs) AS minDuration, MAX(cl.durationMs) AS maxDuration, COUNT(cl.durationMs) AS durationCount')
            ->andWhere('cl.durationMs IS NOT NULL');

        // An aggregate query with no GROUP BY always returns exactly one row, even against an
        // empty (or entirely un-measured) result set - AVG/MIN/MAX come back null and
        // COUNT comes back 0, so this never divides by zero or throws NoResultException.
        $row = $qb->getQuery()->getSingleResult();

        return $this->normalizeDurationRow($row);
    }

    /**
     * @return array<string, int>
     */
    private function countByCommand(CommandLogFilter $filter, ?string $extraCondition = null): array
    {
        $qb = $this->repository->createFilteredQueryBuilder($filter)
            ->select('cl.commandName AS commandName, COUNT(cl.id) AS entryCount')
            ->groupBy('cl.commandName');

        if (null !== $extraCondition) {
            $qb->andWhere($extraCondition);
        }

        $counts = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $counts[$row['commandName']] = (int) $row['entryCount'];
        }

        return $counts;
    }

    /**
     * @return array<string, array{avg: float|null, min: int|null, max: int|null, count: int}>
     */
    private function durationStatisticsByCommand(CommandLogFilter $filter): array
    {
        $qb = $this->repository->createFilteredQueryBuilder($filter)
            ->select('cl.commandName AS commandName, AVG(cl.durationMs) AS avgDuration, MIN(cl.durationMs) AS minDuration, MAX(cl.durationMs) AS maxDuration, COUNT(cl.durationMs) AS durationCount')
            ->andWhere('cl.durationMs IS NOT NULL')
            ->groupBy('cl.commandName');

        $stats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $stats[$row['commandName']] = $this->normalizeDurationRow($row);
        }

        return $stats;
    }

    /**
     * @return array<int, int>
     */
    private function countByExitCode(CommandLogFilter $filter): array
    {
        $qb = $this->repository->createFilteredQueryBuilder($filter)
            ->select('cl.exitCode AS exitCode, COUNT(cl.id) AS entryCount')
            ->andWhere('cl.exitCode IS NOT NULL')
            ->groupBy('cl.exitCode');

        $counts = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $counts[(int) $row['exitCode']] = (int) $row['entryCount'];
        }

        return $counts;
    }

    /**
     * @param array{avgDuration: mixed, minDuration: mixed, maxDuration: mixed, durationCount: mixed} $row
     *
     * @return array{avg: float|null, min: int|null, max: int|null, count: int}
     */
    private function normalizeDurationRow(array $row): array
    {
        return [
            'avg' => null !== $row['avgDuration'] ? (float) $row['avgDuration'] : null,
            'min' => null !== $row['minDuration'] ? (int) $row['minDuration'] : null,
            'max' => null !== $row['maxDuration'] ? (int) $row['maxDuration'] : null,
            'count' => (int) $row['durationCount'],
        ];
    }
}
