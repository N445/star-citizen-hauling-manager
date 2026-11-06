<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CargoLot;
use App\Domain\Crate;
use App\Domain\Mission;
use App\Domain\Route;
use App\Domain\RouteOptimizer;
use App\Domain\Stop;
use App\Domain\TravelTier;
use PHPUnit\Framework\TestCase;

final class RouteOptimizerTest extends TestCase
{
    private RouteOptimizer $optimizer;

    protected function setUp(): void
    {
        $this->optimizer = new RouteOptimizer();
    }

    public function testNoMissionsGivesEmptyRoute(): void
    {
        $route = $this->optimizer->optimize([], DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame([], $route->stops);
        self::assertSame(0, $route->cost);
    }

    public function testAvoidsAbabWhenAabbIsPossible(): void
    {
        $missions = [
            DomainFactory::mission('m1', 'orison', 'area18', 8),
            DomainFactory::mission('m2', 'orison', 'area18', 8),
            DomainFactory::mission('m3', 'orison', 'area18', 4),
        ];

        $route = $this->optimizer->optimize($missions, DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame(['orison', 'area18'], $this->locationIds($route));
        self::assertSame(3, $route->cost);
        self::assertSame(TravelTier::Local, $route->startTier);
        self::assertSame(TravelTier::Stellar, $route->stops[0]->tierToNext);
        $dropMissions = array_unique(array_map(static fn ($c): string => $c->missionId, $route->stops[1]->drops));
        sort($dropMissions);
        self::assertSame(['m1', 'm2', 'm3'], $dropMissions);
    }

    public function testStopsOnSameLocationAreMergedAcrossMissions(): void
    {
        $missions = [
            DomainFactory::mission('m1', 'orison', 'area18', 4),
            DomainFactory::mission('m2', 'area18', 'lorville', 4),
            DomainFactory::mission('m3', 'orison', 'lorville', 4),
            DomainFactory::mission('m4', 'area18', 'lorville', 2),
        ];

        $route = $this->optimizer->optimize($missions, DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame(['orison', 'area18', 'lorville'], $this->locationIds($route));
        self::assertCount(2, $route->stops[0]->pickups);
        self::assertCount(1, $route->stops[1]->drops);
        self::assertCount(2, $route->stops[1]->pickups);
        self::assertCount(3, $route->stops[2]->drops);
    }

    public function testPickupAlwaysBeforeDrop(): void
    {
        $missions = [
            DomainFactory::mission('m1', 'orison', 'area18', 8),
            DomainFactory::mission('m2', 'area18', 'lorville', 8),
            DomainFactory::mission('m3', 'lorville', 'new-babbage', 8),
            DomainFactory::mission('m4', 'new-babbage', 'orison', 8),
            DomainFactory::mission('m5', 'lorville', 'orison', 3),
        ];

        $route = $this->optimizer->optimize($missions, DomainFactory::ship(), DomainFactory::place('area18'));
        $replay = DomainFactory::replay($route);

        self::assertCount(6, $replay['pickupStop']);
        self::assertSame(array_keys($replay['pickupStop']), array_keys($replay['dropStop']));
        foreach ($replay['pickupStop'] as $crateId => $pickupIndex) {
            self::assertLessThan($replay['dropStop'][$crateId], $pickupIndex, $crateId);
        }
    }

    public function testCapacityIsNeverExceeded(): void
    {
        $ship = DomainFactory::ship(2, 2, 2);
        $missions = [
            DomainFactory::mission('m1', 'orison', 'area18', 6),
            DomainFactory::mission('m2', 'orison', 'area18', 6),
            DomainFactory::mission('m3', 'orison', 'area18', 6),
        ];

        $route = $this->optimizer->optimize($missions, $ship, DomainFactory::place('orison'));
        $replay = DomainFactory::replay($route);

        self::assertLessThanOrEqual($ship->capacity(), $replay['peak']);
        self::assertCount(6, $replay['pickupStop']);
        self::assertSame(array_keys($replay['pickupStop']), array_keys($replay['dropStop']));
        self::assertGreaterThan(2, count($route->stops), 'several trips are required');
    }

    public function testStartIsSkippedWhenNothingToDoThere(): void
    {
        $route = $this->optimizer->optimize(
            [DomainFactory::mission('m1', 'area18', 'lorville', 4)],
            DomainFactory::ship(),
            DomainFactory::place('orison'),
        );

        self::assertSame(['area18', 'lorville'], $this->locationIds($route));
        self::assertSame(6, $route->cost);
        self::assertSame(TravelTier::Stellar, $route->startTier);
        self::assertSame(TravelTier::Stellar, $route->stops[0]->tierToNext);
        self::assertNull($route->stops[1]->tierToNext);
    }

    public function testLocalMissionsCostNothingAndVisitEachPlanetOnce(): void
    {
        $missions = [
            DomainFactory::mission('m1', 'orison', 'area18', 4),
            DomainFactory::mission('m2', 'seraphim-station', 'baijini-point', 4),
            DomainFactory::mission('m3', 'baijini-point', 'orison', 2),
            DomainFactory::mission('m4', 'area18', 'seraphim-station', 2),
        ];

        $route = $this->optimizer->optimize($missions, DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame(6, $route->cost, 'one trip to ArcCorp and one back');
        $planets = array_map(static fn (Stop $s): string => $s->location->planet, $route->stops);
        $changes = 0;
        foreach ($route->stops as $i => $stop) {
            if (TravelTier::Stellar === $stop->tierToNext) {
                ++$changes;
            }
        }
        self::assertSame(2, $changes);
        DomainFactory::replay($route);
    }

    public function testMissionsOnOnePlanetAreFreeAndOrderIsFree(): void
    {
        $missions = [
            DomainFactory::mission('m1', 'orison', 'seraphim-station', 4),
            DomainFactory::mission('m2', 'seraphim-station', 'orison', 4),
        ];

        $route = $this->optimizer->optimize($missions, DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame(0, $route->cost);
        self::assertSame(TravelTier::Local, $route->stops[0]->tierToNext);
        DomainFactory::replay($route);
    }

    public function testMoonsAreGroupedBeforeLeavingThePlanet(): void
    {
        $missions = [
            DomainFactory::mission('m1', 'orison', 'kudre-ore', 4),
            DomainFactory::mission('m2', 'kudre-ore', 'lorville', 4),
            DomainFactory::mission('m3', 'orison', 'benson-mining', 4),
        ];

        $route = $this->optimizer->optimize($missions, DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame(1 + 1 + 3, $route->cost);
        self::assertSame('lorville', $route->stops[array_key_last($route->stops)]->location->id);
    }

    public function testExplicitCrateTooBigForTheShipIsRejected(): void
    {
        $from = DomainFactory::place('orison');
        $to = DomainFactory::place('area18');
        $lot = new CargoLot('l1', 'm1', 'Gold', 32, $from, $to, [new Crate('c1', 'l1', 'm1', 32)]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Aucun emplacement pour une caisse de 32 SCU dans Test ship');

        $this->optimizer->optimize([new Mission('m1', 'M', 1, [$lot])], DomainFactory::ship(2, 2, 2), $from);
    }

    public function testAutoSplitAdaptsToTheLargestAcceptedCrate(): void
    {
        $ship = DomainFactory::ship(2, 2, 2);

        $route = $this->optimizer->optimize([DomainFactory::mission('m1', 'orison', 'area18', 32)], $ship, DomainFactory::place('orison'));

        $sizes = [];
        foreach ($route->stops as $stop) {
            foreach ($stop->pickups as $crate) {
                $sizes[] = $crate->sizeScu;
            }
        }
        self::assertSame(32, array_sum($sizes));
        self::assertLessThanOrEqual(8, max($sizes));
        self::assertLessThanOrEqual(8, DomainFactory::replay($route)['peak']);
    }

    /** @return list<string> */
    private function locationIds(Route $route): array
    {
        return array_map(static fn (Stop $stop): string => $stop->location->id, $route->stops);
    }
}
