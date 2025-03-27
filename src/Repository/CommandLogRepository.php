<?php

namespace Ayaou\CommandLoggerBundle\Repository;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandLog>
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
     * Purge logs older than the given date.
     *
     * @param \DateTimeInterface $cutoffDate
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
}