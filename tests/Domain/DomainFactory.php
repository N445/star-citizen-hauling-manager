<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CargoGrid;
use App\Domain\CargoLot;
use App\Domain\Location;
use App\Domain\Mission;
use App\Domain\Route;
use App\Domain\Ship;
use App\Domain\StantonLocations;

final class DomainFactory
{
    public static function place(string $id): Location
    {
        return StantonLocations::get($id);
    }

    public static function ship(int $width = 4, int $length = 6, int $height = 2): Ship
    {
        return new Ship('Test ship', CargoGrid::box($width, $length, $height));
    }

    public static function mission(string $id, string $from, string $to, int $scu, string $resource = 'Laranite'): Mission
    {
        return new Mission($id, 'Mission '.$id, 1000, [
            new CargoLot($id.'-lot', $id, $resource, $scu, self::place($from), self::place($to)),
        ]);
    }

    /**
     * Replays a route; fails when a crate is dropped without being aboard.
     *
     * @return array{peak: int, pickupStop: array<string, int>, dropStop: array<string, int>}
     */
    public static function replay(Route $route): array
    {
        $load = 0;
        $peak = 0;
        $aboard = [];
        $pickupStop = [];
        $dropStop = [];
        foreach ($route->stops as $index => $stop) {
            foreach ($stop->drops as $crate) {
                if (!isset($aboard[$crate->id])) {
                    throw new \LogicException(sprintf('Crate %s dropped before being picked up.', $crate->id));
                }
                unset($aboard[$crate->id]);
                $load -= $crate->sizeScu;
                $dropStop[$crate->id] = $index;
            }
            foreach ($stop->pickups as $crate) {
                $aboard[$crate->id] = true;
                $load += $crate->sizeScu;
                $pickupStop[$crate->id] = $index;
            }
            $peak = max($peak, $load);
        }

        return ['peak' => $peak, 'pickupStop' => $pickupStop, 'dropStop' => $dropStop];
    }
}
