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
use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandLog>
 *
 * @method CommandLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommandLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommandLog[]    findAll()
 * @method CommandLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandLog::class);
    }

    /**
     * Finds a single log by its numeric id, or by its execution token when the value is not
     * numeric. The API exposes both identifiers under the same {id} route parameter.
     */
    public function findOneByIdOrToken(string $id): ?CommandLog
    {
        if (ctype_digit($id)) {
            return $this->find((int) $id);
        }

        return $this->findOneBy(['executionToken' => $id]);
    }

    /**
     * Purge logs older than the given date.
     *
     * @return int Number of deleted rows
     */
    public function purgeLogsOlderThan(\DateTimeInterface $cutoffDate): int
    {
        $qb = $this->createQueryBuilder('cl')
            ->delete()
            ->where('cl.startTime < :cutoff')
            ->setParameter('cutoff', $cutoffDate);

        return $qb->getQuery()->execute();
    }

    /**
     * @return Paginator<CommandLog>
     */
    public function getPaginatedResults(CommandLogFilter $filter): Paginator
    {
        $qb = $this->applyFilter($this->createQueryBuilder('cl'), $filter)
            ->orderBy('cl.startTime', 'DESC')
            ->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        return new Paginator($qb);
    }

    /**
     * Aggregate statistics over every log matching the filter: volume by outcome, failure
     * rate, and duration extrema. Deliberately built from nothing but COUNT/AVG/MIN/MAX and
     * GROUP BY - no date function, no percentile - so the same query runs unchanged on the
     * three database engines this bundle targets. The failure rate is computed here in PHP
     * rather than in SQL, sidestepping the division-by-zero (and cross-engine differences
     * handling it) that a SQL-side ratio would risk on an empty result set.
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

    /**
     * Applies the CommandLogFilter criteria (name, status/code, from/to) to a query builder.
     * Shared by getPaginatedResults(), getStatistics() and getStatisticsByCommand() so the
     * statistics filter exactly the same rows the list endpoint would show for the same
     * filter.
     */
    private function applyFilter(QueryBuilder $qb, CommandLogFilter $filter): QueryBuilder
    {
        if ($filter->name) {
            $qb->andWhere('cl.commandName LIKE :name')
                ->setParameter('name', '%'.$filter->name.'%');
        }

        if ($filter->status) {
            if ('error' === $filter->status) {
                $qb->andWhere('cl.exitCode != 0');
            } else {
                $qb->andWhere('cl.exitCode = 0');
            }
        } elseif (null !== $filter->code) {
            $qb->andWhere('cl.exitCode = :exitCode')
                ->setParameter('exitCode', $filter->code);
        }

        if ($filter->from) {
            $qb->andWhere('cl.startTime >= :from')
                ->setParameter('from', $filter->getFromDate());
        }

        if ($filter->to) {
            $qb->andWhere('cl.startTime <= :to')
                ->setParameter('to', $filter->getToDate());
        }

        return $qb;
    }

    private function countMatching(CommandLogFilter $filter, ?string $extraCondition = null): int
    {
        $qb = $this->applyFilter($this->createQueryBuilder('cl')->select('COUNT(cl.id)'), $filter);

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
        $qb = $this->applyFilter(
            $this->createQueryBuilder('cl')->select(
                'AVG(cl.durationMs) AS avgDuration, MIN(cl.durationMs) AS minDuration, MAX(cl.durationMs) AS maxDuration, COUNT(cl.durationMs) AS durationCount',
            ),
            $filter,
        )->andWhere('cl.durationMs IS NOT NULL');

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
        $qb = $this->applyFilter(
            $this->createQueryBuilder('cl')->select('cl.commandName AS commandName, COUNT(cl.id) AS entryCount'),
            $filter,
        )->groupBy('cl.commandName');

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
        $qb = $this->applyFilter(
            $this->createQueryBuilder('cl')->select(
                'cl.commandName AS commandName, AVG(cl.durationMs) AS avgDuration, MIN(cl.durationMs) AS minDuration, MAX(cl.durationMs) AS maxDuration, COUNT(cl.durationMs) AS durationCount',
            ),
            $filter,
        )->andWhere('cl.durationMs IS NOT NULL')->groupBy('cl.commandName');

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
        $qb = $this->applyFilter(
            $this->createQueryBuilder('cl')->select('cl.exitCode AS exitCode, COUNT(cl.id) AS entryCount'),
            $filter,
        )->andWhere('cl.exitCode IS NOT NULL')->groupBy('cl.exitCode');

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
