<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\Tour;

final readonly class TourBlueprint
{
    /**
     * @param list<MissionBlueprint> $missions
     * @param list<string>|null      $stopOrder
     */
    public function __construct(
        private string $name,
        private int $capacity,
        private ?string $start,
        private array $missions,
        private ?ShipBlueprint $ship = null,
        private ?array $stopOrder = null,
    ) {
    }

    public function tour(): Tour
    {
        $tour = new Tour($this->name, 'Vaisseau de test', $this->capacity, $this->ship?->build(), $this->start);
        $missionIds = new IdSequence();
        $deliveryIds = new IdSequence();

        foreach ($this->missions as $mission) {
            $tour->addMission($mission->build($missionIds, $deliveryIds));
        }

        $this->applyStopOrder($tour);

        return $tour;
    }

    /**
     * @param list<string> $stopOrder
     */
    public function withStopOrder(array $stopOrder, string $description): self
    {
        return new self($this->name.' · '.$description, $this->capacity, $this->start, $this->missions, $this->ship, $stopOrder);
    }

    public function withCapacity(int $capacity, string $description): self
    {
        return new self($this->name.' · '.$description, $capacity, $this->start, $this->missions, $this->ship, $this->stopOrder);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function largestMission(): int
    {
        return max(array_map(static fn (MissionBlueprint $mission): int => $mission->total(), $this->missions));
    }

    private function applyStopOrder(Tour $tour): void
    {
        if (null === $this->stopOrder) {
            return;
        }

        $tour->reorderStops($this->stopOrder);
    }
}
