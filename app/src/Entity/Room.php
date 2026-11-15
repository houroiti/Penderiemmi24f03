<?php

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A room (pièce) of a home. It belongs to the owner of its home.
 */
#[ORM\Entity(repositoryClass: RoomRepository::class)]
class Room implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Home $home = null;

    /**
     * Close friends can see the (non personal) items stored in a shared room.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isSharedWithCloseFriends = false;

    /**
     * @var Collection<int, Furniture>
     */
    #[ORM\OneToMany(targetEntity: Furniture::class, mappedBy: 'room', orphanRemoval: true)]
    private Collection $furniture;

    public function __construct()
    {
        $this->furniture = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s · %s', $this->home, $this->name);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHome(): ?Home
    {
        return $this->home;
    }

    public function setHome(?Home $home): static
    {
        $this->home = $home;

        return $this;
    }

    public function isSharedWithCloseFriends(): bool
    {
        return $this->isSharedWithCloseFriends;
    }

    public function setIsSharedWithCloseFriends(bool $isSharedWithCloseFriends): static
    {
        $this->isSharedWithCloseFriends = $isSharedWithCloseFriends;

        return $this;
    }

    /**
     * @return Collection<int, Furniture>
     */
    public function getFurniture(): Collection
    {
        return $this->furniture;
    }

    public function getOwner(): ?User
    {
        return $this->home?->getOwner();
    }
}
