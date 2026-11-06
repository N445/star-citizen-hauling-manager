<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Stop
{
    /**
     * @param list<Crate>     $pickups
     * @param list<Crate>     $drops
     * @param TravelTier|null $tierToNext travel tier towards the next stop, null on the last stop
     */
    public function __construct(
        public Location $location,
        public array $pickups = [],
        public array $drops = [],
        public ?TravelTier $tierToNext = null,
    ) {
    }

    public function withTierToNext(?TravelTier $tier): self
    {
        return new self($this->location, $this->pickups, $this->drops, $tier);
    }
}
