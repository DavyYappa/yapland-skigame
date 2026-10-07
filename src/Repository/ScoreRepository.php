<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Score;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Score>
 */
class ScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Score::class);
    }

    /** @return list<Score> */
    public function top(Client $client, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.client = :client')
            ->setParameter('client', $client)
            ->orderBy('s.points', 'DESC')
            ->addOrderBy('s.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countFor(Client $client): int
    {
        return $this->count(['client' => $client]);
    }

    public function deleteAll(): int
    {
        return $this->createQueryBuilder('s')->delete()->getQuery()->execute();
    }
}
