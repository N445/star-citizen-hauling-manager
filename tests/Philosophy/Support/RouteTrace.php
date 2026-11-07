<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Service\Route\Route;

final class RouteTrace
{
    /** @var array<int, list<int>> */
    private array $pickupSteps = [];

    /** @var array<int, list<string>> */
    private array $pickupLocations = [];

    /** @var array<int, list<int>> */
    private array $deliverySteps = [];

    public function __construct(private readonly Route $route)
    {
        foreach ($route->stops as $step => $stop) {
            $this->recordPickups($stop->pickups, $step, $stop->location);
            $this->recordDeliveries($stop->deliveries, $step);
        }
    }

    /**
     * @return list<int>
     */
    public function pickupStepsOf(Mission $mission): array
    {
        return $this->pickupSteps[spl_object_id($mission)] ?? [];
    }

    /**
     * @return list<string>
     */
    public function pickupLocationsOf(Mission $mission): array
    {
        return $this->pickupLocations[spl_object_id($mission)] ?? [];
    }

    /**
     * @return list<int>
     */
    public function deliveryStepsOf(Delivery $delivery): array
    {
        return $this->deliverySteps[spl_object_id($delivery)] ?? [];
    }

    public function firstPickupStepOf(Mission $mission): int
    {
        return min([PHP_INT_MAX, ...$this->pickupStepsOf($mission)]);
    }

    public function deliveryStepOf(Delivery $delivery): int
    {
        return min([PHP_INT_MAX, ...$this->deliveryStepsOf($delivery)]);
    }

    /**
     * @param list<Mission> $missions
     */
    public function loadAfter(int $step, array $missions): int
    {
        return array_sum(array_map(fn (Mission $mission): int => $this->loadOfMissionAfter($mission, $step), $missions));
    }

    /**
     * @param list<Mission> $missions
     */
    public function peakLoad(array $missions): int
    {
        return max([0, ...array_map(fn (int $step): int => $this->loadAfter($step, $missions), array_keys($this->route->stops))]);
    }

    /**
     * @param list<Mission> $missions
     *
     * @return list<Mission>
     */
    public function missionsInProgressAfter(int $step, array $missions): array
    {
        return array_values(array_filter($missions, fn (Mission $mission): bool => $this->isStartedAfter($mission, $step) && $this->remainingAfter($mission, $step) > 0));
    }

    private function loadOfMissionAfter(Mission $mission, int $step): int
    {
        return $this->isStartedAfter($mission, $step) ? $this->remainingAfter($mission, $step) : 0;
    }

    private function isStartedAfter(Mission $mission, int $step): bool
    {
        return $this->firstPickupStepOf($mission) <= $step;
    }

    private function remainingAfter(Mission $mission, int $step): int
    {
        $pending = array_filter($mission->deliveries()->getValues(), fn (Delivery $delivery): bool => $this->deliveryStepOf($delivery) > $step);

        return array_sum(array_map(static fn (Delivery $delivery): int => $delivery->effectiveQuantity(), $pending));
    }

    /**
     * @param list<Mission> $missions
     */
    private function recordPickups(array $missions, int $step, string $location): void
    {
        foreach ($missions as $mission) {
            $this->pickupSteps[spl_object_id($mission)][] = $step;
            $this->pickupLocations[spl_object_id($mission)][] = $location;
        }
    }

    /**
     * @param list<Delivery> $deliveries
     */
    private function recordDeliveries(array $deliveries, int $step): void
    {
        foreach ($deliveries as $delivery) {
            $this->deliverySteps[spl_object_id($delivery)][] = $step;
        }
    }
}
