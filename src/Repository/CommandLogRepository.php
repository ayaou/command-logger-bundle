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
        $qb = $this->createQueryBuilder('cl')
            ->orderBy('cl.startTime', 'DESC');

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

        $qb->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        return new Paginator($qb);
    }
}
