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
}
