<?php

namespace App\Entity;

use App\Enum\LoanStatus;
use App\Repository\LoanRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A loan (prêt) of an item. Its status is never stored: it is deduced from the dates.
 */
#[ORM\Entity(repositoryClass: LoanRepository::class)]
class Loan implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Item $item = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $borrowerName = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $lentAt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'lentAt', message: 'loan.due_before_lent')]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'lentAt', message: 'loan.returned_before_lent')]
    private ?\DateTimeImmutable $returnedAt = null;

    public function __construct()
    {
        $this->lentAt = new \DateTimeImmutable('today');
    }

    /**
     * Returned if a return date exists; overdue if the due date is past; in progress otherwise.
     */
    public function getStatus(?\DateTimeImmutable $today = null): LoanStatus
    {
        $today ??= new \DateTimeImmutable('today');

        if (null !== $this->returnedAt) {
            return LoanStatus::Returned;
        }

        if (null !== $this->dueDate && $this->dueDate < $today) {
            return LoanStatus::Overdue;
        }

        return LoanStatus::InProgress;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        $this->item = $item;

        return $this;
    }

    public function getBorrowerName(): ?string
    {
        return $this->borrowerName;
    }

    public function setBorrowerName(?string $borrowerName): static
    {
        $this->borrowerName = $borrowerName;

        return $this;
    }

    public function getLentAt(): ?\DateTimeImmutable
    {
        return $this->lentAt;
    }

    public function setLentAt(?\DateTimeImmutable $lentAt): static
    {
        $this->lentAt = $lentAt;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?\DateTimeImmutable $returnedAt): static
    {
        $this->returnedAt = $returnedAt;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->item?->getOwner();
    }
}
