<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Cargo;

use App\Service\Cargo\CargoPlanner;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\CargoComfortInvariants;
use App\Tests\Philosophy\Support\CargoLoadProfile;
use App\Tests\Philosophy\Support\CargoSafetyInvariants;
use App\Tests\Philosophy\Support\RandomTours;
use App\Tests\Philosophy\Support\TourBlueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CargoPhilosophyTest extends TestCase
{
    private const int FIRST_SEED = 3001;

    private const int CASE_COUNT = 120;

    /**
     * @return iterable<string, array{TourBlueprint, RouteMode}>
     */
    public static function generatedTours(): iterable
    {
        foreach (range(self::FIRST_SEED, self::FIRST_SEED + self::CASE_COUNT - 1) as $seed) {
            $blueprint = (new RandomTours($seed))->cargoTour();
            $mode = 0 === $seed % 3 ? RouteMode::MissionFocus : RouteMode::FewestStops;
            yield $blueprint->name().' · '.$mode->label() => [$blueprint, $mode];
        }
    }

    /**
     * @return iterable<string, array{TourBlueprint, RouteMode}>
     */
    public static function spaciousTours(): iterable
    {
        return self::toursWhere(static fn (CargoLoadProfile $profile): bool => $profile->isSpacious());
    }

    /**
     * @return iterable<string, array{TourBlueprint, RouteMode}>
     */
    public static function spaciousToursWithSideDoors(): iterable
    {
        return self::toursWhere(static fn (CargoLoadProfile $profile): bool => $profile->isSpacious() && $profile->hasOnlyHorizontalDoors());
    }

    /**
     * @return iterable<string, array{TourBlueprint, RouteMode}>
     */
    public static function spaciousToursWithOneDoor(): iterable
    {
        return self::toursWhere(static fn (CargoLoadProfile $profile): bool => $profile->isSpacious() && $profile->hasSingleHorizontalDoor() && $profile->loadsSeveralDestinationsAtOnce());
    }

    /**
     * @return iterable<string, array{TourBlueprint, RouteMode}>
     */
    public static function roomyTours(): iterable
    {
        return self::toursWhere(static fn (CargoLoadProfile $profile): bool => $profile->isRoomy());
    }

    #[DataProvider('generatedTours')]
    public function testEveryCrateIsSafelyStowed(TourBlueprint $blueprint, RouteMode $mode): void
    {
        $tour = $blueprint->tour();
        $route = (new RoutePlanner())->plan($tour, $mode);
        $plan = (new CargoPlanner())->plan($tour, $route);

        (new CargoSafetyInvariants($tour, $route, $plan, $blueprint->name()))->assertAll();
    }

    #[DataProvider('roomyTours')]
    public function testRoomyHoldFindsRoomForEveryScu(TourBlueprint $blueprint, RouteMode $mode): void
    {
        $this->comfort($blueprint, $mode)->assertEverythingPlaced();
    }

    #[DataProvider('spaciousTours')]
    public function testSpaciousHoldNeverStacksOnAnEarlierDelivery(TourBlueprint $blueprint, RouteMode $mode): void
    {
        $this->comfort($blueprint, $mode)->assertNothingStacked();
    }

    #[DataProvider('spaciousToursWithSideDoors')]
    public function testSpaciousHoldKeepsEachDestinationInOneBlock(TourBlueprint $blueprint, RouteMode $mode): void
    {
        $this->comfort($blueprint, $mode)->assertSameDestinationFormsOneBlock();
    }

    #[DataProvider('spaciousToursWithOneDoor')]
    public function testSpaciousHoldPutsEarlierDeliveriesNearerTheDoor(TourBlueprint $blueprint, RouteMode $mode): void
    {
        $this->comfort($blueprint, $mode)->assertEarlierDeliveriesCloserToTheDoor();
    }

    /**
     * @param callable(CargoLoadProfile): bool $condition
     *
     * @return iterable<string, array{TourBlueprint, RouteMode}>
     */
    private static function toursWhere(callable $condition): iterable
    {
        foreach (self::generatedTours() as $name => [$blueprint, $mode]) {
            $tour = $blueprint->tour();

            if ($condition(new CargoLoadProfile($tour, (new RoutePlanner())->plan($tour, $mode)))) {
                yield $name => [$blueprint, $mode];
            }
        }
    }

    private function comfort(TourBlueprint $blueprint, RouteMode $mode): CargoComfortInvariants
    {
        $tour = $blueprint->tour();
        $route = (new RoutePlanner())->plan($tour, $mode);

        return new CargoComfortInvariants($tour, $route, (new CargoPlanner())->plan($tour, $route), $blueprint->name().' · '.$mode->label());
    }
}
