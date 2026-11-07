<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RouteReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Erreur ou bug déclaré sur une route. Le snapshot est figé au moment de la déclaration : il ne dépend plus de la tournée.
 */
#[ORM\Entity(repositoryClass: RouteReportRepository::class)]
#[ORM\Index(columns: ['status'])]
class RouteReport
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
     * @param ?Tour $tour   effacé quand la tournée l'est : le rapport reste, avec son instantané
     * @param ?User $author null pour un visiteur sans compte (alors `guestToken`) ; supprimé avec le compte
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?Tour $tour,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?User $author,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $guestToken,
        #[ORM\Column(length: 20, enumType: ReportCategory::class)]
        private ReportCategory $category,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::JSON)]
        private array $snapshot,
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

    public function tour(): ?Tour
    {
        return $this->tour;
    }

    public function author(): ?User
    {
        return $this->author;
    }

    public function guestToken(): ?string
    {
        return $this->guestToken;
    }

    public function category(): ReportCategory
    {
        return $this->category;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->snapshot;
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
