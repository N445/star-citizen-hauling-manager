<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Delivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $delivered = false;

    /** quantité réellement livrée, saisie en route ; null = l'estimation de la mission */
    #[ORM\Column(nullable: true)]
    private ?int $actualQuantity = null;

    #[ORM\ManyToOne(inversedBy: 'deliveries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Mission $mission;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $dropoff,
        #[ORM\Column(length: 80)]
        private string $resource,
        #[ORM\Column]
        private int $quantity,
    ) {
    }

    public function attachTo(Mission $mission): void
    {
        $this->mission = $mission;
    }

    public function toggleDelivered(): void
    {
        $this->delivered = !$this->delivered;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function isDelivered(): bool
    {
        return $this->delivered;
    }

    public function mission(): Mission
    {
        return $this->mission;
    }

    public function dropoff(): string
    {
        return $this->dropoff;
    }

    public function resource(): string
    {
        return $this->resource;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    /**
     * La quantité qui compte pour la capacité et le chargement de la soute.
     */
    public function effectiveQuantity(): int
    {
        return $this->actualQuantity ?? $this->quantity;
    }

    public function hasActualQuantity(): bool
    {
        return null !== $this->actualQuantity;
    }

    public function adjustQuantity(?int $quantity): void
    {
        $this->actualQuantity = $quantity;
    }
}
