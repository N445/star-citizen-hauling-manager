<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Placement
{
    /**
     * @param list<PlacedCrate> $crates
     * @param list<string>      $ignoredPins ids of pinned crates whose pin could not be honoured
     */
    public function __construct(
        public Route $route,
        public Ship $ship,
        public array $crates,
        private array $ignoredPins = [],
    ) {
    }

    public function forCrate(string $crateId): PlacedCrate
    {
        foreach ($this->crates as $placed) {
            if ($placed->crate->id === $crateId) {
                return $placed;
            }
        }

        throw new \InvalidArgumentException(sprintf('Crate "%s" is not placed.', $crateId));
    }

    /** @return list<string> */
    public function ignoredPins(): array
    {
        return $this->ignoredPins;
    }

    /**
     * Crates visible in the grid at a stop: aboard on arrival plus those loaded there.
     *
     * @return list<PlacedCrate>
     */
    public function gridAt(int $stopIndex): array
    {
        return array_values(array_filter(
            $this->crates,
            static fn (PlacedCrate $p): bool => $p->pickupStop <= $stopIndex && $stopIndex <= $p->dropStop,
        ));
    }

    /** @return list<PlacedCrate> */
    public function toPickupAt(int $stopIndex): array
    {
        return array_values(array_filter($this->crates, static fn (PlacedCrate $p): bool => $p->pickupStop === $stopIndex));
    }

    /** @return list<PlacedCrate> */
    public function toDropAt(int $stopIndex): array
    {
        return array_values(array_filter($this->crates, static fn (PlacedCrate $p): bool => !$p->kept && $p->dropStop === $stopIndex));
    }

    /**
     * Null when the crate can be put there for its whole stay aboard, else a French refusal message.
     *
     * @param int $orientation index into Crate::orientations()
     */
    public function canPlace(string $crateId, int $x, int $y, int $z, int $orientation): ?string
    {
        $current = $this->find($crateId);
        if (null === $current) {
            return 'Caisse inconnue.';
        }

        return $this->occupancyWithout($crateId)->reason($current->crate, $current->pickupStop, $current->dropStop, $orientation, $x, $y, $z);
    }

    /**
     * @param int $orientation index into Crate::orientations()
     *
     * @throws \DomainException with the refusal message
     */
    public function move(string $crateId, int $x, int $y, int $z, int $orientation): self
    {
        $reason = $this->canPlace($crateId, $x, $y, $z, $orientation);
        if (null !== $reason) {
            throw new \DomainException($reason);
        }

        $current = $this->forCrate($crateId);
        [$w, $l, $h] = $current->crate->orientations()[$orientation];
        $block = $this->occupancyWithout($crateId)->blockIndex($x, $y, $z, $w, $l, $h) ?? 0;
        $moved = new PlacedCrate($current->crate, $x, $y, $z, $w, $l, $h, $current->pickupStop, $current->dropStop, $block, $orientation, $current->kept);

        return new self(
            $this->route,
            $this->ship,
            array_map(static fn (PlacedCrate $p): PlacedCrate => $p->crate->id === $crateId ? $moved : $p, $this->crates),
            $this->ignoredPins,
        );
    }

    /**
     * Every position where the crate could be dropped (origin + orientation index), including its current one.
     *
     * @return list<array{x: int, y: int, z: int, orientation: int}>
     */
    public function validPositions(string $crateId): array
    {
        $current = $this->find($crateId) ?? throw new \InvalidArgumentException(sprintf('Crate "%s" is not placed.', $crateId));
        $occupancy = $this->occupancyWithout($crateId);
        $positions = [];

        foreach ($this->ship->grid->blocks as $b => $block) {
            if (!$block->accepts($current->crate)) {
                continue;
            }
            foreach ($current->crate->orientations() as $o => [$w, $l, $h]) {
                for ($z = $block->z; $z + $h <= $block->z + $block->height; ++$z) {
                    for ($y = $block->y; $y + $l <= $block->y + $block->length; ++$y) {
                        for ($x = $block->x; $x + $w <= $block->x + $block->width; ++$x) {
                            if ($occupancy->isFree($b, $x, $y, $z, $w, $l, $h, $current->pickupStop, $current->dropStop)) {
                                $positions[] = ['x' => $x, 'y' => $y, 'z' => $z, 'orientation' => $o];
                            }
                        }
                    }
                }
            }
        }

        return $positions;
    }

    /**
     * @return array{w: int, l: int, h: int}
     */
    public function footprint(string $crateId, int $orientation): array
    {
        $crate = ($this->find($crateId) ?? throw new \InvalidArgumentException(sprintf('Crate "%s" is not placed.', $crateId)))->crate;
        $dimensions = $crate->orientations()[$orientation] ?? throw new \InvalidArgumentException('Orientation invalide.');

        return ['w' => $dimensions[0], 'l' => $dimensions[1], 'h' => $dimensions[2]];
    }

    private function find(string $crateId): ?PlacedCrate
    {
        foreach ($this->crates as $placed) {
            if ($placed->crate->id === $crateId) {
                return $placed;
            }
        }

        return null;
    }

    private function occupancyWithout(string $crateId): CargoOccupancy
    {
        $occupancy = new CargoOccupancy($this->ship->grid);
        foreach ($this->crates as $placed) {
            if ($placed->crate->id !== $crateId) {
                $occupancy->add($placed);
            }
        }

        return $occupancy;
    }
}
