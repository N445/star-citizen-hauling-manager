<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Retour libre d'un visiteur (amélioration, bug, question) envoyé depuis la page de contact.
 * L'email n'est gardé que si le visiteur le donne pour obtenir une réponse.
 */
#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
#[ORM\Index(columns: ['status'])]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: ReportStatus::class)]
    private ReportStatus $status = ReportStatus::Open;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    /**
     * @param ?User $author null pour un visiteur sans compte (alors `guestToken`) ; le message survit à la suppression du compte,
     *                     rendu anonyme ({@see ContactMessageRepository::anonymizeAuthor})
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $author,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $guestToken,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $replyEmail,
        #[ORM\Column(length: 20, enumType: ContactCategory::class)]
        private ContactCategory $category,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(length: 5)]
        private string $locale,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function resolve(\DateTimeImmutable $now): void
    {
        $this->status = ReportStatus::Resolved;
        $this->resolvedAt = $now;
    }

    public function reopen(): void
    {
        $this->status = ReportStatus::Open;
        $this->resolvedAt = null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function author(): ?User
    {
        return $this->author;
    }

    public function replyEmail(): ?string
    {
        return $this->replyEmail;
    }

    public function category(): ContactCategory
    {
        return $this->category;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(): ReportStatus
    {
        return $this->status;
    }

    public function isResolved(): bool
    {
        return ReportStatus::Resolved === $this->status;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
