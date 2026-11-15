<?php

namespace App\Repository;

use App\Entity\Loan;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * Loans not returned yet (in progress or overdue).
     */
    public function countActive(?User $owner = null): int
    {
        return (int) $this->activeLoans($owner)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Same rule as Loan::getStatus(): not returned and due date before today.
     */
    public function countOverdue(\DateTimeImmutable $today, ?User $owner = null): int
    {
        return (int) $this->activeLoans($owner)
            ->select('COUNT(l.id)')
            ->andWhere('l.dueDate < :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function activeLoans(?User $owner): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->andWhere('l.returnedAt IS NULL');

        if (null !== $owner) {
            $queryBuilder
                ->join('l.item', 'i')
                ->andWhere('i.owner = :owner')
                ->setParameter('owner', $owner);
        }

        return $queryBuilder;
    }
}
