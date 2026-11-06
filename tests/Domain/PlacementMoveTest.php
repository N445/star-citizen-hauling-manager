<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CargoBlock;
use App\Domain\CargoGrid;
use App\Domain\CargoPlacer;
use App\Domain\Crate;
use App\Domain\DoorSide;
use App\Domain\PlacedCrate;
use App\Domain\Pin;
use App\Domain\Placement;
use App\Domain\Route;
use App\Domain\RouteOptimizer;
use App\Domain\Ship;
use App\Domain\ShipCatalog;
use App\Domain\Stop;
use PHPUnit\Framework\TestCase;

final class PlacementMoveTest extends TestCase
{
    /** a: stops 0-2, b: stops 0-2 (overlapping), c: stops 3-4 (disjoint from a and b). */
    private function threeCrateRoute(): array
    {
        $a = new Crate('a', 'l', 'm1', 4);
        $b = new Crate('b', 'l', 'm1', 4);
        $c = new Crate('c', 'l', 'm2', 4);
        $route = new Route([
            new Stop(DomainFactory::place('orison'), [$a, $b]),
            new Stop(DomainFactory::place('area18')),
            new Stop(DomainFactory::place('lorville'), [], [$a, $b]),
            new Stop(DomainFactory::place('orison'), [$c]),
            new Stop(DomainFactory::place('area18'), [], [$c]),
        ], 0);

        return [$route, [$a, $b, $c]];
    }

    private function placement(): Placement
    {
        [$route] = $this->threeCrateRoute();
        $ship = new Ship('Test', CargoGrid::box(4, 4, 1));

        return (new CargoPlacer())->place($route, $ship);
    }

    public function testValidMoveIsImmutable(): void
    {
        $placement = $this->placement();
        $free = null;
        foreach ($placement->validPositions('a') as $pos) {
            if (0 === $pos['orientation'] && ($pos['x'] !== $placement->forCrate('a')->x || $pos['y'] !== $placement->forCrate('a')->y)) {
                $free = $pos;
                break;
            }
        }
        self::assertNotNull($free);
        self::assertNull($placement->canPlace('a', $free['x'], $free['y'], $free['z'], 0));

        $moved = $placement->move('a', $free['x'], $free['y'], $free['z'], 0);

        self::assertSame($free['x'], $moved->forCrate('a')->x);
        self::assertNotSame([$free['x'], $free['y']], [$placement->forCrate('a')->x, $placement->forCrate('a')->y]);
        self::assertSame($free['y'], $moved->forCrate('a')->y);
        self::assertCount(3, $moved->crates);
    }

    public function testOverlapRefusedOnlyWhenIntervalsOverlap(): void
    {
        $placement = $this->placement();
        $b = $placement->forCrate('b');
        $c = $placement->forCrate('c');

        $message = $placement->canPlace('a', $b->x, $b->y, $b->z, 0);
        self::assertSame('Chevauche la caisse b.', $message);

        // c lives on stops 3-4, a and b on 0-2: sharing their cell is fine.
        self::assertNull($placement->canPlace('c', $b->x, $b->y, $b->z, 0));
        self::assertNull($placement->canPlace('a', $c->x, $c->y, $c->z, 0));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Chevauche la caisse b.');
        $placement->move('a', $b->x, $b->y, $b->z, 0);
    }

    public function testOutOfHoldAndBadOrientationAndUnknownCrate(): void
    {
        $placement = $this->placement();

        self::assertSame('La caisse sort de la soute.', $placement->canPlace('a', 3, 0, 0, 0));
        self::assertSame('La caisse sort de la soute.', $placement->canPlace('a', 0, 0, 1, 0));
        self::assertSame('Orientation invalide.', $placement->canPlace('a', 0, 0, 0, 9));
        self::assertSame('Caisse inconnue.', $placement->canPlace('zzz', 0, 0, 0, 0));
        self::assertSame(['w' => 2, 'l' => 2, 'h' => 1], $placement->footprint('a', 0));
    }

    public function testStraddlingTwoBlocksAndMaxSize(): void
    {
        $grid = new CargoGrid([
            new CargoBlock(0, 0, 0, 2, 2, 1),
            new CargoBlock(2, 0, 0, 2, 2, 1, 2),
        ]);
        $ship = new Ship('Two blocks', $grid);
        $crate = new Crate('a', 'l', 'm', 4);
        $route = new Route([new Stop(DomainFactory::place('orison'), [$crate]), new Stop(DomainFactory::place('area18'), [], [$crate])], 3);
        $placement = (new CargoPlacer())->place($route, $ship);

        self::assertSame(0, $placement->forCrate('a')->blockIndex);
        self::assertSame('La caisse est à cheval sur deux blocs de soute.', $placement->canPlace('a', 1, 0, 0, 0));
        self::assertStringContainsString('dépasse la taille maximale', (string) $placement->canPlace('a', 2, 0, 0, 0));
        self::assertSame([['x' => 0, 'y' => 0, 'z' => 0, 'orientation' => 0]], $placement->validPositions('a'));
    }

    public function testPinsAreHonouredAndInvalidOnesIgnored(): void
    {
        [$route] = $this->threeCrateRoute();
        $ship = new Ship('Test', CargoGrid::box(4, 4, 1));

        $placement = (new CargoPlacer())->place($route, $ship, [
            new Pin('a', 2, 2, 0, 0),
            new Pin('ghost', 0, 0, 0, 0),
            new Pin('b', 2, 2, 0, 0),
            new Pin('c', 9, 9, 0, 0),
        ]);

        $a = $placement->forCrate('a');
        self::assertSame([2, 2], [$a->x, $a->y]);
        self::assertSame(['ghost', 'b', 'c'], $placement->ignoredPins());
        self::assertNull($placement->canPlace('b', $placement->forCrate('b')->x, $placement->forCrate('b')->y, 0, 0));
        self::assertCount(3, $placement->crates);
        self::assertSame([], (new CargoPlacer())->place($route, $ship)->ignoredPins());
    }

    public function testDoorOverrideChangesLifoSide(): void
    {
        $base = new Ship('Corridor', CargoGrid::box(2, 12, 1));
        $missions = [
            DomainFactory::mission('m1', 'orison', 'seraphim-station', 4),
            DomainFactory::mission('m2', 'orison', 'area18', 4),
        ];

        foreach ([DoorSide::Rear, DoorSide::Front] as $door) {
            $ship = $base->withDoor($door);
            $route = (new RouteOptimizer())->optimize($missions, $ship, DomainFactory::place('orison'));
            $placement = (new CargoPlacer())->place($route, $ship);
            $first = $placement->forCrate('m1-lot-1');
            $last = $placement->forCrate('m2-lot-1');

            self::assertSame($door, $placement->ship->grid->door);
            if (DoorSide::Rear === $door) {
                self::assertGreaterThan($last->y, $first->y);
            } else {
                self::assertLessThan($last->y, $first->y);
            }
        }
    }

    public function testValidPositionsOnHullCIsFast(): void
    {
        $ship = ShipCatalog::get('misc-hull-c');
        $route = (new RouteOptimizer())->optimize([DomainFactory::mission('m1', 'orison', 'area18', 500)], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);
        $id = $placement->crates[0]->crate->id;

        $start = microtime(true);
        $positions = $placement->validPositions($id);
        $elapsed = microtime(true) - $start;

        self::assertLessThan(1.0, $elapsed);
        self::assertNotSame([], $positions);
        $first = $positions[0];
        self::assertNull($placement->canPlace($id, $first['x'], $first['y'], $first['z'], $first['orientation']));
    }

    public function testPlacedCrateKeepsOrientationIndex(): void
    {
        $placed = $this->placement()->forCrate('a');

        self::assertInstanceOf(PlacedCrate::class, $placed);
        self::assertSame(0, $placed->orientation);
    }
}
