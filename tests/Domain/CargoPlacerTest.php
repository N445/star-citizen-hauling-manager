<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CargoBlock;
use App\Domain\CargoGrid;
use App\Domain\CargoLot;
use App\Domain\CargoPlacer;
use App\Domain\Crate;
use App\Domain\DoorSide;
use App\Domain\Mission;
use App\Domain\PlacedCrate;
use App\Domain\Route;
use App\Domain\RouteOptimizer;
use App\Domain\Ship;
use App\Domain\ShipCatalog;
use App\Domain\Stop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CargoPlacerTest extends TestCase
{
    public function testLifoCratesDroppedFirstAreNearestToTheDoor(): void
    {
        $ship = DomainFactory::ship(2, 12, 1);
        $route = (new RouteOptimizer())->optimize([
            DomainFactory::mission('m1', 'orison', 'seraphim-station', 4),
            DomainFactory::mission('m2', 'orison', 'area18', 4),
            DomainFactory::mission('m3', 'orison', 'lorville', 4),
        ], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);

        $depthByDrop = [];
        foreach ($placement->crates as $placed) {
            $depthByDrop[$placed->dropStop] = $this->depth($ship, $placed);
        }
        ksort($depthByDrop);

        self::assertCount(3, $depthByDrop);
        $sorted = array_values($depthByDrop);
        sort($sorted);
        self::assertSame($sorted, array_values($depthByDrop), 'later drops are deeper in the hold');
        self::assertCount(3, array_unique($depthByDrop));
    }

    public function testCratesOfAMissionStayGrouped(): void
    {
        $ship = DomainFactory::ship(4, 6, 2);
        $route = (new RouteOptimizer())->optimize([
            DomainFactory::mission('m1', 'orison', 'area18', 12),
            DomainFactory::mission('m2', 'orison', 'area18', 12),
        ], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);

        $order = array_map(static fn (PlacedCrate $p): string => $p->crate->missionId, $placement->crates);
        self::assertSame(['m1', 'm1', 'm2', 'm2'], $order);
    }

    public function testNoOverlapAndInsideGridAtEveryStop(): void
    {
        $ship = DomainFactory::ship(4, 6, 2);
        $route = (new RouteOptimizer())->optimize([
            DomainFactory::mission('m1', 'orison', 'area18', 20),
            DomainFactory::mission('m2', 'area18', 'lorville', 14),
            DomainFactory::mission('m3', 'orison', 'lorville', 9),
            DomainFactory::mission('m4', 'lorville', 'orison', 12),
        ], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);

        foreach (array_keys($route->stops) as $stop) {
            $cells = [];
            foreach ($placement->gridAt($stop) as $p) {
                self::assertGreaterThanOrEqual(0, $p->x);
                self::assertLessThanOrEqual($ship->grid->width, $p->x + $p->width);
                self::assertLessThanOrEqual($ship->grid->length, $p->y + $p->length);
                self::assertLessThanOrEqual($ship->grid->height, $p->z + $p->height);
                self::assertSame($p->crate->sizeScu, $p->width * $p->length * $p->height);
                for ($i = $p->x; $i < $p->x + $p->width; ++$i) {
                    for ($j = $p->y; $j < $p->y + $p->length; ++$j) {
                        for ($k = $p->z; $k < $p->z + $p->height; ++$k) {
                            $key = "$i/$j/$k";
                            self::assertArrayNotHasKey($key, $cells, "overlap at stop $stop");
                            $cells[$key] = true;
                        }
                    }
                }
            }
        }
    }

    public function testPlacementExposesStopState(): void
    {
        $ship = DomainFactory::ship();
        $route = (new RouteOptimizer())->optimize([
            DomainFactory::mission('m1', 'orison', 'area18', 4),
            DomainFactory::mission('m2', 'area18', 'lorville', 2),
        ], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);

        self::assertCount(3, $route->stops);
        self::assertCount(1, $placement->toPickupAt(0));
        self::assertCount(0, $placement->toDropAt(0));
        self::assertCount(1, $placement->gridAt(0));
        self::assertCount(1, $placement->toDropAt(1));
        self::assertCount(1, $placement->toPickupAt(1));
        self::assertCount(2, $placement->gridAt(1));
        self::assertCount(1, $placement->gridAt(2));
        self::assertSame('m2', $placement->gridAt(2)[0]->crate->missionId);
        self::assertSame('m1-lot-1', $placement->forCrate('m1-lot-1')->crate->id);
    }

    public function testDoorSideDefinesDepth(): void
    {
        $ship = new Ship('Left door', CargoGrid::box(6, 2, 1, DoorSide::Left));
        $route = (new RouteOptimizer())->optimize([
            DomainFactory::mission('m1', 'orison', 'seraphim-station', 2),
            DomainFactory::mission('m2', 'orison', 'area18', 2),
        ], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);

        self::assertLessThan($placement->forCrate('m2-lot-1')->x, $placement->forCrate('m1-lot-1')->x);
    }

    /** @return iterable<string, array{string}> */
    public static function bigShips(): iterable
    {
        foreach (['drake-ironclad', 'misc-hull-c', 'aegis-idris-m', 'drake-cutlass-black'] as $slug) {
            yield $slug => [$slug];
        }
    }

    #[DataProvider('bigShips')]
    public function testLargeLotOnMultiBlockShipIsFastAndConsistent(string $slug): void
    {
        $ship = ShipCatalog::get($slug);
        $scu = min(500, $ship->capacity() - 20);
        $start = microtime(true);

        $route = (new RouteOptimizer())->optimize([DomainFactory::mission('m1', 'orison', 'area18', $scu)], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);

        self::assertLessThan(2.0, microtime(true) - $start);
        self::assertSame($scu, array_sum(array_map(static fn (PlacedCrate $p): int => $p->crate->sizeScu, $placement->crates)));
        $cells = [];
        foreach ($placement->crates as $p) {
            $block = $ship->grid->blocks[$p->blockIndex];
            self::assertTrue($block->contains($p->x, $p->y, $p->z));
            self::assertTrue($block->contains($p->x + $p->width - 1, $p->y + $p->length - 1, $p->z + $p->height - 1), 'crate straddles blocks');
            if (null !== $block->maxSize) {
                self::assertLessThanOrEqual($block->maxSize, $p->crate->sizeScu);
            }
            for ($i = $p->x; $i < $p->x + $p->width; ++$i) {
                for ($j = $p->y; $j < $p->y + $p->length; ++$j) {
                    for ($k = $p->z; $k < $p->z + $p->height; ++$k) {
                        self::assertArrayNotHasKey("$i/$j/$k", $cells);
                        $cells["$i/$j/$k"] = true;
                    }
                }
            }
        }
    }

    public function testMaxSizeBlockRefusesBigCrate(): void
    {
        $ship = new Ship('Limited', new CargoGrid([new CargoBlock(0, 0, 0, 8, 2, 2, 16)]));
        $from = DomainFactory::place('orison');
        $lot = new CargoLot('l1', 'm1', 'Gold', 32, $from, DomainFactory::place('area18'), [new Crate('c1', 'l1', 'm1', 32)]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Aucun emplacement pour une caisse de 32 SCU dans Limited');

        (new RouteOptimizer())->optimize([new Mission('m1', 'M', 1, [$lot])], $ship, $from);
    }

    public function testPlacerRefusesCrateWithoutSpot(): void
    {
        $ship = new Ship('Limited', new CargoGrid([new CargoBlock(0, 0, 0, 8, 2, 2, 16)]));
        $crate = new Crate('c1', 'l1', 'm1', 32);
        $route = new Route([new Stop(DomainFactory::place('orison'), [$crate]), new Stop(DomainFactory::place('area18'), [], [$crate])], 3);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Aucun emplacement pour une caisse de 32 SCU dans Limited');

        (new CargoPlacer())->place($route, $ship);
    }

    public function testCrateNeverStraddlesTwoBlocks(): void
    {
        $ship = new Ship('Split', new CargoGrid([new CargoBlock(0, 0, 0, 1, 2, 1), new CargoBlock(1, 0, 0, 1, 2, 1)]));
        $crate = new Crate('c1', 'l1', 'm1', 4);
        $route = new Route([new Stop(DomainFactory::place('orison'), [$crate]), new Stop(DomainFactory::place('area18'), [], [$crate])], 3);

        $this->expectException(\DomainException::class);

        (new CargoPlacer())->place($route, $ship);
    }

    private function depth(Ship $ship, PlacedCrate $p): int
    {
        $min = PHP_INT_MAX;
        for ($i = $p->x; $i < $p->x + $p->width; ++$i) {
            for ($j = $p->y; $j < $p->y + $p->length; ++$j) {
                $min = min($min, $ship->grid->doorDistance($i, $j));
            }
        }

        return $min;
    }
}
