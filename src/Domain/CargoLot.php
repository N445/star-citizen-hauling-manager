<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class CargoLot
{
    /**
     * @param Location|null $dropoff null: the lot is never dropped and stays aboard until the end of the route
     * @param list<Crate>   $crates
     */
    public function __construct(
        public string $id,
        public string $missionId,
        public string $resource,
        public int $scu,
        public Location $pickup,
        public ?Location $dropoff,
        public array $crates = [],
    ) {
        if ($scu < 0) {
            throw new \InvalidArgumentException('A lot cannot have a negative quantity.');
        }
    }

    public function withCrates(int $maxCrateSize = 32): self
    {
        if ([] !== $this->crates || 0 === $this->scu) {
            return $this;
        }

        return new self($this->id, $this->missionId, $this->resource, $this->scu, $this->pickup, $this->dropoff, Crate::split($this, $maxCrateSize));
    }
}
