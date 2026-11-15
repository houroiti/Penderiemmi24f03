<?php

namespace App\Entity;

use App\Repository\FurnitureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A piece of furniture (meuble) in a room: wardrobe, shelf, chest of drawers...
 */
#[ORM\Entity(repositoryClass: FurnitureRepository::class)]
class Furniture implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'furniture')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Room $room = null;

    public function __toString(): string
    {
        return sprintf('%s · %s', $this->room, $this->name);
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

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->room?->getOwner();
    }
}
