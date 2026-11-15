<?php

namespace App\Entity;

use App\Repository\FollowRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * "follower follows followed". Two levels, as asked by the client:
 * close friend (see + comment) or simple follower (see only what is opened to followers).
 */
#[ORM\Entity(repositoryClass: FollowRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_follow_pair', columns: ['follower_id', 'followed_id'])]
#[UniqueEntity(fields: ['follower', 'followed'])]
class Follow implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $follower = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Assert\NotIdenticalTo(propertyPath: 'follower', message: 'follow.self')]
    private ?User $followed = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isCloseFriend = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFollower(): ?User
    {
        return $this->follower;
    }

    public function setFollower(?User $follower): static
    {
        $this->follower = $follower;

        return $this;
    }

    public function getFollowed(): ?User
    {
        return $this->followed;
    }

    public function setFollowed(?User $followed): static
    {
        $this->followed = $followed;

        return $this;
    }

    public function isCloseFriend(): bool
    {
        return $this->isCloseFriend;
    }

    public function setIsCloseFriend(bool $isCloseFriend): static
    {
        $this->isCloseFriend = $isCloseFriend;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * The person being followed decides who sees their things: they own the relation.
     */
    public function getOwner(): ?User
    {
        return $this->followed;
    }
}
