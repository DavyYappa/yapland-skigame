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

    /** @return list<Invitation> new invitations of contacts who did not opt out */
    public function findToMail(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :new')
            ->andWhere('i.unsubscribedAt IS NULL')
            ->setParameter('new', InvitationStatus::New)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnsubscribed(): int
    {
        return $this->count([]) - $this->count(['unsubscribedAt' => null]);
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

    /**
     * @param list<string> $emails
     *
     * @return array<string, bool> the ones that already exist, with whether they opted out
     */
    public function existingEmails(array $emails): array
    {
        if ([] === $emails) {
            return [];
        }

        $rows = $this->createQueryBuilder('i')
            ->select('i.email, i.unsubscribedAt')
            ->andWhere('i.email IN (:emails)')
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getArrayResult();

        return array_combine(array_column($rows, 'email'), array_map(static fn ($r) => null !== $r['unsubscribedAt'], $rows));
    }
}
