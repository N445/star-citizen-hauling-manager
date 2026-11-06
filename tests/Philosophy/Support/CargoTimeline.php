<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\PlacedCrate;

final readonly class CargoTimeline
{
    public function __construct(private CargoPlan $plan)
    {
    }

    /**
     * @return list<PlacedCrate>
     */
    public function crates(): array
    {
        return $this->plan->crates;
    }

    /**
     * @return list<int>
     */
    public function steps(): array
    {
        $steps = array_map(static fn (PlacedCrate $crate): int => $crate->pickupStep, $this->plan->crates);

        return array_values(array_unique($steps));
    }

    /**
     * @return list<PlacedCrate>
     */
    public function aboardAt(int $step): array
    {
        return array_values(array_filter($this->plan->crates, static fn (PlacedCrate $crate): bool => self::isAboard($crate, $step)));
    }

    /**
     * @return list<PlacedCrate>
     */
    public function aboardWhenLoading(int $crateIndex): array
    {
        $crate = $this->plan->crates[$crateIndex];
        $earlier = \array_slice($this->plan->crates, 0, $crateIndex);

        return array_values(array_filter($earlier, static fn (PlacedCrate $other): bool => self::isAboard($other, $crate->pickupStep)));
    }

    /**
     * @return list<PlacedCrate>
     */
    public function loadedAt(int $step): array
    {
        return array_values(array_filter($this->plan->crates, static fn (PlacedCrate $crate): bool => $crate->pickupStep === $step));
    }

    /**
     * @return list<string>
     */
    public function destinationsAlreadyAboard(int $step): array
    {
        $carriedOver = array_filter($this->aboardAt($step), static fn (PlacedCrate $crate): bool => $crate->pickupStep < $step);

        return array_values(array_unique(array_map(static fn (PlacedCrate $crate): string => $crate->delivery->dropoff(), $carriedOver)));
    }

    public static function dropStepOf(PlacedCrate $crate): int
    {
        return $crate->dropStep ?? PHP_INT_MAX;
    }

    private static function isAboard(PlacedCrate $crate, int $step): bool
    {
        return $crate->pickupStep <= $step && $step < self::dropStepOf($crate);
    }
}
