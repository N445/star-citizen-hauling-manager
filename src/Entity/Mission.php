<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Mission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'missions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tour $tour;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $pickedUpAt = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @var list<array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int, manual?: bool, placed?: bool}>|null emplacements figés (au ramassage, ou posés à la main) */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $cargoPlacement = null;

    /** @var array<string, int> quantité réellement ramassée par lieu, saisie en route (absent = pas saisie) */
    #[ORM\Column(type: Types::JSON)]
    private array $pickupQuantities = [];

    /** @var Collection<int, Delivery> */
    #[ORM\OneToMany(targetEntity: Delivery::class, mappedBy: 'mission', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $deliveries;

    /**
     * @param list<string>   $pickups    en ramassages multiples, la quantité livrée est le total, sans répartition par lieu
     * @param list<Delivery> $deliveries
     */
    public function __construct(
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $name,
        #[ORM\Column]
        private int $reward,
        #[ORM\Column(length: 20, enumType: MissionType::class)]
        private MissionType $type,
        #[ORM\Column(type: Types::JSON)]
        private array $pickups,
        array $deliveries,
    ) {
        $this->deliveries = new ArrayCollection();
        $this->replaceDeliveries($deliveries);
    }

    /**
     * @param list<string>   $pickups
     * @param list<Delivery> $deliveries
     */
    public function update(?string $name, int $reward, MissionType $type, array $pickups, array $deliveries): void
    {
        $this->name = $name;
        $this->reward = $reward;
        $this->type = $type;
        $this->pickups = $pickups;
        $this->pickedUpAt = array_values(array_intersect($this->pickedUpAt, $pickups));
        $this->cargoPlacement = null;
        $this->pickupQuantities = [];
        $this->finishedAt = null;
        $this->replaceDeliveries($deliveries);
    }

    /**
     * Chronomètre caché : départ au premier ramassage ou livraison coché, arrivée quand tout est coché.
     */
    public function recordProgress(\DateTimeImmutable $now): void
    {
        $this->startedAt = $this->hasProgress() ? ($this->startedAt ?? $now) : null;
        $this->finishedAt = $this->isDone() ? ($this->finishedAt ?? $now) : null;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function durationInSeconds(): ?int
    {
        if (null === $this->startedAt || null === $this->finishedAt) {
            return null;
        }

        return $this->finishedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }

    public function togglePickupAt(string $location): void
    {
        if ($this->isPickedUpAt($location)) {
            $this->pickedUpAt = array_values(array_diff($this->pickedUpAt, [$location]));
            $this->cargoPlacement = [] === $this->pickedUpAt && !$this->hasManualCargo() ? null : $this->cargoPlacement;

            return;
        }

        $this->pickedUpAt[] = $location;
    }

    /**
     * @param list<array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int, manual?: bool, placed?: bool}> $placement
     */
    public function freezeCargo(array $placement): void
    {
        $this->cargoPlacement = $placement;
    }

    public function clearCargo(): void
    {
        $this->cargoPlacement = null;
    }

    public function hasManualCargo(): bool
    {
        return array_any($this->cargoPlacement ?? [], static fn (array $crate): bool => $crate['manual'] ?? false);
    }

    public function hasPickedUpAnything(): bool
    {
        return [] !== $this->pickedUpAt;
    }

    /**
     * @return list<array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int, manual?: bool, placed?: bool}>|null
     */
    public function frozenCargo(): ?array
    {
        return $this->cargoPlacement;
    }

    public function isPickedUpAt(string $location): bool
    {
        return \in_array($location, $this->pickedUpAt, true);
    }

    private function hasProgress(): bool
    {
        return [] !== $this->pickedUpAt || $this->deliveries->exists(static fn (int $key, Delivery $delivery): bool => $delivery->isDelivered());
    }

    public function isDone(): bool
    {
        $allPickedUp = [] === array_diff($this->pickups, $this->pickedUpAt);

        return $allPickedUp && $this->deliveries->forAll(static fn (int $key, Delivery $delivery): bool => $delivery->isDelivered());
    }

    public function attachTo(Tour $tour): void
    {
        $this->tour = $tour;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tour(): Tour
    {
        return $this->tour;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function reward(): int
    {
        return $this->reward;
    }

    public function type(): MissionType
    {
        return $this->type;
    }

    /**
     * @return list<string>
     */
    public function pickups(): array
    {
        return $this->pickups;
    }

    /**
     * @return Collection<int, Delivery>
     */
    public function deliveries(): Collection
    {
        return $this->deliveries;
    }

    public function totalQuantity(): int
    {
        return array_sum($this->deliveries->map(static fn (Delivery $delivery): int => $delivery->quantity())->toArray());
    }

    /**
     * Total réel : les quantités saisies en route remplacent l'estimation.
     */
    public function effectiveQuantity(): int
    {
        return array_sum($this->deliveries->map(static fn (Delivery $delivery): int => $delivery->effectiveQuantity())->toArray());
    }

    /**
     * @return array<string, int>
     */
    public function pickupQuantities(): array
    {
        return $this->pickupQuantities;
    }

    public function pickupQuantitySum(): int
    {
        return array_sum(array_intersect_key($this->pickupQuantities, array_flip($this->pickups)));
    }

    public function pickupQuantity(string $location): ?int
    {
        return $this->pickupQuantities[$location] ?? null;
    }

    public function recordPickupQuantity(string $location, ?int $quantity): void
    {
        if (null === $quantity) {
            unset($this->pickupQuantities[$location]);

            return;
        }

        $this->pickupQuantities[$location] = $quantity;
    }

    /**
     * Les caisses déjà placées ne correspondent plus : on repart du calcul automatique.
     */
    public function quantitiesChanged(): void
    {
        $this->cargoPlacement = null;
    }

    /**
     * @param list<Delivery> $deliveries
     */
    private function replaceDeliveries(array $deliveries): void
    {
        $this->deliveries->clear();

        foreach ($deliveries as $delivery) {
            $delivery->attachTo($this);
            $this->deliveries->add($delivery);
        }
    }
}
