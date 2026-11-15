<?php

namespace App\Repository;

use App\Entity\Follow;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Follow>
 */
class FollowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follow::class);
    }

    /**
     * The relation "$follower follows $followed", or null if there is none.
     */
    public function findRelation(User $follower, User $followed): ?Follow
    {
        return $this->findOneBy(['follower' => $follower, 'followed' => $followed]);
    }
}
