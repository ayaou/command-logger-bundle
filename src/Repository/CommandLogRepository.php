<?php

namespace Ayaou\CommandLoggerBundle\Repository;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Purge logs older than the given date.
     *
     * @return int Number of deleted rows
     * @throws \Exception If the cutoff date is in the future
     */
    public function purgeLogsOlderThan(\DateTimeInterface $cutoffDate): int
    {
        // Safety check to prevent accidental deletion of recent logs
        $now = new \DateTimeImmutable();
        if ($cutoffDate > $now) {
            throw new \InvalidArgumentException('Cutoff date cannot be in the future');
        }

        $qb = $this->createQueryBuilder('cl')
            ->delete()
            ->where('cl.startTime < :cutoff')
            ->setParameter('cutoff', $cutoffDate);

        return $qb->getQuery()->execute();
    }

    /**
     * Find logs by command name with optional filters.
     *
     * @param array<string, mixed> $filters
     * @return CommandLog[]
     */
    public function findByFilters(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('cl');

        if (isset($filters['commandName']) && !empty($filters['commandName'])) {
            $qb->andWhere('cl.commandName = :commandName')
                ->setParameter('commandName', $filters['commandName']);
        }

        if (isset($filters['exitCode']) && is_numeric($filters['exitCode'])) {
            $qb->andWhere('cl.exitCode = :exitCode')
                ->setParameter('exitCode', (int) $filters['exitCode']);
        }

        if (isset($filters['hasError']) && $filters['hasError'] === true) {
            $qb->andWhere('cl.exitCode != 0 OR cl.errorMessage IS NOT NULL');
        }

        return $qb->orderBy('cl.startTime', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
