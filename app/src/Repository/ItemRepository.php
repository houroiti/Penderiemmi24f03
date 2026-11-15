<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Furniture;
use App\Entity\Home;
use App\Entity\Room;
use App\Entity\User;
use App\Enum\ItemCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * Number of items, optionally only those of one owner.
     */
    public function countItems(?User $owner = null): int
    {
        return $this->count(null === $owner ? [] : ['owner' => $owner]);
    }

    public function countPersonal(?User $owner = null): int
    {
        return $this->count(null === $owner ? ['isPersonal' => true] : ['isPersonal' => true, 'owner' => $owner]);
    }

    /**
     * Items not stored in any furniture yet ("à ranger"), optionally only those of one owner.
     */
    public function countUnstored(?User $owner = null): int
    {
        return $this->count(null === $owner ? ['furniture' => null] : ['owner' => $owner, 'furniture' => null]);
    }

    /**
     * @return array<string, int> category value => number of items
     */
    public function countByCategory(): array
    {
        /** @var list<array{category: ItemCategory, total: int}> $rows */
        $rows = $this->createQueryBuilder('i')
            ->select('i.category AS category, COUNT(i.id) AS total')
            ->groupBy('i.category')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['category']->value] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Number of items stored in a home, a room or a piece of furniture
     * (shown before deleting it: these items will become "to put away").
     */
    public function countStoredIn(Home|Room|Furniture $place): int
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.furniture', 'f');

        match (true) {
            $place instanceof Furniture => $queryBuilder->andWhere('f = :place'),
            $place instanceof Room => $queryBuilder->andWhere('f.room = :place'),
            $place instanceof Home => $queryBuilder->join('f.room', 'r')->andWhere('r.home = :place'),
        };

        return (int) $queryBuilder->setParameter('place', $place)->getQuery()->getSingleScalarResult();
    }
}
