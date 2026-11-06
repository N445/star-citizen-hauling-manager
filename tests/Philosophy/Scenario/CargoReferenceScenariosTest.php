<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Scenario;

use App\Entity\DoorSide;
use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\CargoPlanner;
use App\Service\Cargo\PlacedCrate;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\CargoComfortInvariants;
use App\Tests\Philosophy\Support\CargoSafetyInvariants;
use App\Tests\Philosophy\Support\HandWrittenTours;
use App\Tests\Philosophy\Support\TourBlueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CargoReferenceScenariosTest extends TestCase
{
    /**
     * @return iterable<string, array{TourBlueprint}>
     */
    public static function spaciousLoadAllThenDeliver(): iterable
    {
        $cases = [
            HandWrittenTours::cargoOn('crusader-c2-hercules', [DoorSide::Rear], 4, 20),
            HandWrittenTours::cargoOn('crusader-c2-hercules', [DoorSide::Front], 5, 12),
            HandWrittenTours::cargoOn('argo-raft', [DoorSide::Rear], 3, 15),
            HandWrittenTours::cargoOn('argo-raft', [DoorSide::Left], 4, 9),
            HandWrittenTours::cargoOn('synthetique-couloir', [DoorSide::Rear], 3, 5),
            HandWrittenTours::cargoOn('synthetique-couloir', [DoorSide::Front], 3, 5),
            HandWrittenTours::cargoOn('rsi-hermes', [DoorSide::Rear], 4, 16),
            HandWrittenTours::cargoOn('synthetique-plat', [DoorSide::Right], 3, 8),
        ];

        foreach ($cases as $blueprint) {
            yield $blueprint->name() => [$blueprint];
        }
    }

    /**
     * @return iterable<string, array{TourBlueprint}>
     */
    public static function demandingHolds(): iterable
    {
        $cases = [
            HandWrittenTours::nearlyFull('drake-cutlass-black'),
            HandWrittenTours::nearlyFull('synthetique-surelevee'),
            HandWrittenTours::nearlyFull('synthetique-taille-minimale'),
            HandWrittenTours::nearlyFull('rsi-constellation-mk-iv-taurus'),
            HandWrittenTours::nearlyFull('drake-caterpillar'),
            HandWrittenTours::nearlyFull('synthetique-colonne'),
            HandWrittenTours::cargoOn('drake-caterpillar', [DoorSide::Left, DoorSide::Right], 4, 60),
            HandWrittenTours::cargoOn('origin-300i', [DoorSide::Rear], 1, 7),
        ];

        foreach ($cases as $blueprint) {
            yield $blueprint->name() => [$blueprint];
        }
    }

    #[DataProvider('spaciousLoadAllThenDeliver')]
    public function testSpaciousHoldIsLoadedLikeADeliveryVan(TourBlueprint $blueprint): void
    {
        $comfort = $this->comfort($blueprint);

        $comfort->assertEverythingPlaced();
        $comfort->assertNothingStacked();
        $comfort->assertEarlierDeliveriesCloserToTheDoor();
        $comfort->assertSameDestinationFormsOneBlock();
    }

    #[DataProvider('demandingHolds')]
    public function testDemandingHoldStaysPhysicallyValid(TourBlueprint $blueprint): void
    {
        $tour = $blueprint->tour();
        $route = (new RoutePlanner())->plan($tour, RouteMode::FewestStops);

        (new CargoSafetyInvariants($tour, $route, (new CargoPlanner())->plan($tour, $route), $blueprint->name()))->assertAll();
    }

    public function testCaterpillarWithSideDoorsKeepsRoomyCargoUnstacked(): void
    {
        $comfort = $this->comfort(HandWrittenTours::cargoOn('drake-caterpillar', [DoorSide::Left, DoorSide::Right], 4, 25));

        $comfort->assertEverythingPlaced();
        $comfort->assertNothingStacked();
        $comfort->assertSameDestinationFormsOneBlock();
    }

    public function testMinimumSizeGridOnlyReceivesLargeEnoughCrates(): void
    {
        $plan = $this->cargoPlan(HandWrittenTours::nearlyFull('synthetique-taille-minimale'));
        $smallCratesInRestrictedGrid = array_filter($plan->crates, static fn (PlacedCrate $crate): bool => $crate->size < 4 && 4 === $crate->spot->bay->width);

        self::assertSame([], array_values($smallCratesInRestrictedGrid), 'taille minimale : une caisse de moins de 4 SCU a été posée dans la grille qui les refuse');
    }

    public function testSmallShipSplitsIntoCratesThatFitItsHeight(): void
    {
        $plan = $this->cargoPlan(HandWrittenTours::cargoOn('origin-300i', [DoorSide::Rear], 1, 7));
        $sizes = array_map(static fn (PlacedCrate $crate): int => $crate->size, $plan->crates);
        rsort($sizes);

        self::assertSame([4, 2, 1], $sizes, '300i (1 SCU de haut) : 7 SCU doivent tenir en caisses de 4, 2 et 1');
    }

    private function comfort(TourBlueprint $blueprint): CargoComfortInvariants
    {
        $tour = $blueprint->tour();
        $route = (new RoutePlanner())->plan($tour, RouteMode::FewestStops);
        $plan = (new CargoPlanner())->plan($tour, $route);
        (new CargoSafetyInvariants($tour, $route, $plan, $blueprint->name()))->assertAll();

        return new CargoComfortInvariants($tour, $route, $plan, $blueprint->name());
    }

    private function cargoPlan(TourBlueprint $blueprint): CargoPlan
    {
        $tour = $blueprint->tour();

        return (new CargoPlanner())->plan($tour, (new RoutePlanner())->plan($tour, RouteMode::FewestStops));
    }
}
