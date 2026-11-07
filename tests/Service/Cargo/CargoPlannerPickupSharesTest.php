<?php

declare(strict_types=1);

namespace App\Tests\Service\Cargo;

use App\Entity\Delivery;
use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\Ship;
use App\Entity\Tour;
use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\CargoPlanner;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\IdSequence;
use PHPUnit\Framework\TestCase;

/**
 * Ramassage multiple dont le joueur a saisi les quantités par lieu : chaque lieu charge sa part, pas toute la mission.
 */
final class CargoPlannerPickupSharesTest extends TestCase
{
    private Mission $mission;

    /**
     * @param array<string, int> $pickupQuantities
     */
    private function tour(array $pickupQuantities, int $estimate = 12): Tour
    {
        $ship = new Ship('Test', 'Cube', 48, [['x' => 0, 'z' => 0, 'grids' => [['x' => 0, 'y' => 0, 'z' => 0, 'width' => 4, 'height' => 2, 'length' => 6]]]]);
        $ship->changeDoors([DoorSide::Rear]);
        $tour = new Tour('Test', 'Cube', 48, $ship, 'Lorville');
        $delivery = new Delivery('Area 18', 'Gold', $estimate);
        (new IdSequence())->assign($delivery);
        $this->mission = new Mission(null, 1000, MissionType::MultiplePickUp, ['Lorville', 'Orison'], [$delivery]);
        (new IdSequence())->assign($this->mission);
        $tour->addMission($this->mission);

        foreach ($pickupQuantities as $location => $quantity) {
            $this->mission->recordPickupQuantity($location, $quantity);
        }

        return $tour;
    }

    private function plan(Tour $tour): CargoPlan
    {
        return (new CargoPlanner())->plan($tour, (new RoutePlanner())->plan($tour, RouteMode::FewestStops));
    }

    /**
     * @return array<string, int>
     */
    private function loadedBy(CargoPlan $plan): array
    {
        $loaded = [];

        foreach ($plan->placementOf((int) $this->mission->id()) as $crate) {
            $loaded[$crate['pickup']] = ($loaded[$crate['pickup']] ?? 0) + $crate['size'];
        }

        ksort($loaded);

        return $loaded;
    }

    public function testEachPickupLoadsItsOwnQuantity(): void
    {
        self::assertSame(['Lorville' => 2, 'Orison' => 10], $this->loadedBy($this->plan($this->tour(['Lorville' => 2, 'Orison' => 10]))));
    }

    public function testCheckingTheFirstPickupDoesNotBringTheOtherCratesAboard(): void
    {
        $tour = $this->tour(['Lorville' => 2, 'Orison' => 10]);
        $this->mission->togglePickupAt('Lorville');
        $this->mission->freezeCargo($this->plan($tour)->placementOf((int) $this->mission->id()));

        $plan = $this->plan($tour);

        self::assertSame(['Lorville' => 2, 'Orison' => 10], $this->loadedBy($plan));
        $aboard = array_filter($plan->visibleCrates(), static fn ($crate): bool => !$crate->isPhantom());
        self::assertSame(2, array_sum(array_map(static fn ($crate): int => $crate->size, $aboard)));
    }

    public function testRouteShowsWhatIsReallyAboardAtEachStop(): void
    {
        $route = (new RoutePlanner())->plan($this->tour(['Lorville' => 2, 'Orison' => 10]), RouteMode::FewestStops);
        $loads = array_combine(array_map(static fn ($stop): string => $stop->location, $route->stops), array_map(static fn ($stop): ?int => $stop->load, $route->stops));

        self::assertSame(['Lorville' => 2, 'Orison' => 12, 'Area 18' => 0], $loads);
    }

    public function testUnknownPickupTakesWhatIsLeftOfTheEstimate(): void
    {
        self::assertSame(['Lorville' => 2, 'Orison' => 10], $this->loadedBy($this->plan($this->tour(['Orison' => 10]))));
    }

    public function testWithoutQuantitiesEverythingIsLoadedAtTheFirstPickup(): void
    {
        $loaded = $this->loadedBy($this->plan($this->tour([])));

        self::assertSame([12], array_values($loaded));
        self::assertCount(1, $loaded);
    }

    public function testOldFrozenPlacementWithoutPickupIsRecalculated(): void
    {
        $tour = $this->tour(['Lorville' => 2, 'Orison' => 10]);
        $this->mission->togglePickupAt('Lorville');
        $legacy = array_map(static function (array $crate): array {
            unset($crate['pickup']);

            return $crate;
        }, $this->plan($tour)->placementOf((int) $this->mission->id()));
        $this->mission->freezeCargo($legacy);

        self::assertSame(['Lorville' => 2, 'Orison' => 10], $this->loadedBy($this->plan($tour)));
    }
}
