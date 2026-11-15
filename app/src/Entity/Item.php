<?php

namespace App\Entity;

use App\Enum\ItemCategory;
use App\Repository\ItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Index(name: 'idx_item_category', columns: ['category'])]
class Item implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['item:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['item:read'])]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['item:read'])]
    private bool $isPersonal = false;

    // deleting a user deletes their items (and the loans of those items): right to erasure
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['item:read'])]
    private ?User $owner = null;

    #[ORM\Column]
    #[Groups(['item:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20, enumType: ItemCategory::class, options: ['default' => 'other'])]
    #[Groups(['item:read'])]
    private ItemCategory $category = ItemCategory::Other;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    #[Groups(['item:read'])]
    private ?string $color = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['item:read'])]
    private ?string $brand = null;

    /**
     * Where the item is stored. Null = not put away yet.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Furniture $furniture = null;

    /**
     * Followers (not close friends) only see the items their owner opened to them.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isVisibleToFollowers = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return (string) $this->name;
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

    public function isPersonal(): bool
    {
        return $this->isPersonal;
    }

    public function setIsPersonal(bool $isPersonal): static
    {
        $this->isPersonal = $isPersonal;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCategory(): ItemCategory
    {
        return $this->category;
    }

    public function setCategory(ItemCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getFurniture(): ?Furniture
    {
        return $this->furniture;
    }

    public function setFurniture(?Furniture $furniture): static
    {
        $this->furniture = $furniture;

        return $this;
    }

    /**
     * A personal item is never shared: it cannot be opened to followers at the same time.
     * The form greys the box out, but only the server can be trusted (rule 4 of the audit).
     */
    #[Assert\Callback]
    public function validatePersonalIsNotShared(ExecutionContextInterface $context): void
    {
        if ($this->isPersonal && $this->isVisibleToFollowers) {
            $context->buildViolation('item.personal_not_shared')
                ->atPath('isVisibleToFollowers')
                ->addViolation();
        }
    }

    public function isVisibleToFollowers(): bool
    {
        return $this->isVisibleToFollowers;
    }

    public function setIsVisibleToFollowers(bool $isVisibleToFollowers): static
    {
        $this->isVisibleToFollowers = $isVisibleToFollowers;

        return $this;
    }
}
