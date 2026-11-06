<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\Delivery;
use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\Tour;
use App\Service\Route\Route;

final readonly class CargoLoadProfile
{
    private const float SPACIOUS_RATIO = 0.3;

    private const float ROOMY_RATIO = 0.5;

    private CargoRoom $room;

    private int $peakLoad;

    public function __construct(private Tour $tour, private Route $route)
    {
        $this->room = CargoRoom::of($tour->ship());
        $this->peakLoad = (new RouteTrace($route))->peakLoad($tour->missions()->getValues());
    }

    public function isSpacious(): bool
    {
        return $this->peakLoad <= self::SPACIOUS_RATIO * $this->room->floorCapacityForSingleUnits();
    }

    public function isRoomy(): bool
    {
        return $this->peakLoad <= self::ROOMY_RATIO * $this->room->volumeForSingleUnits();
    }

    public function hasSingleHorizontalDoor(): bool
    {
        return 1 === \count($this->tour->doorSides()) && $this->hasOnlyHorizontalDoors();
    }

    public function hasOnlyHorizontalDoors(): bool
    {
        $verticalDoors = array_filter($this->tour->doorSides(), static fn (DoorSide $door): bool => 'y' === $door->axis());

        return [] === $verticalDoors;
    }

    public function loadsSeveralDestinationsAtOnce(): bool
    {
        $trace = new RouteTrace($this->route);

        foreach (array_keys($this->route->stops) as $step) {
            $loaded = array_filter($this->tour->missions()->getValues(), static fn (Mission $mission): bool => $trace->firstPickupStepOf($mission) === $step);
            $dropoffs = array_merge([], ...array_map(static fn (Mission $mission): array => $mission->deliveries()->map(static fn (Delivery $delivery): string => $delivery->dropoff())->getValues(), $loaded));

            if (\count(array_unique($dropoffs)) > 1) {
                return true;
            }
        }

        return false;
    }

    public function describe(): string
    {
        return sprintf('charge max %d SCU, sol utile %d SCU, volume utile %d SCU', $this->peakLoad, $this->room->floorCapacityForSingleUnits(), $this->room->volumeForSingleUnits());
    }
}
