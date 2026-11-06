<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Route
{
    /**
     * @param list<Stop>      $stops
     * @param TravelTier|null $startTier travel tier from the starting location to the first stop
     */
    public function __construct(
        public array $stops,
        public int $cost,
        public ?TravelTier $startTier = null,
    ) {
    }
}
