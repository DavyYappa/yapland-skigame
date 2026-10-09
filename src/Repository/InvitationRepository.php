<?php

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\InvitationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    /** @return list<Invitation> */
    public function findAllNewestFirst(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** @return list<Invitation> */
    public function findToMail(): array
    {
        return $this->findBy(['status' => InvitationStatus::New], ['id' => 'ASC']);
    }

    /** @return array<string, int> count per status value */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.status AS status, COUNT(i.id) AS total')
            ->groupBy('i.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof InvitationStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['total'];
        }

        return $counts;
    }

    /** @param list<string> $emails @return list<string> the ones that already exist */
    public function existingEmails(array $emails): array
    {
        if ([] === $emails) {
            return [];
        }

        return array_column($this->createQueryBuilder('i')
            ->select('i.email')
            ->andWhere('i.email IN (:emails)')
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getArrayResult(), 'email');
    }
}
