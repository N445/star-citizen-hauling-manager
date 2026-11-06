<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\DoorSide;
use App\Entity\Ship;

final class ShipFixtures
{
    /** @var array<string, array{label: string, manufacturer: string, name: string, doors: list<string>, cargoGroups: list<array<string, mixed>>}>|null */
    private static ?array $ships = null;

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @param list<DoorSide> $doors
     */
    public static function build(string $key, array $doors): Ship
    {
        $fixture = self::all()[$key];
        $ship = new Ship($fixture['manufacturer'], $fixture['name'], self::volume($key), $fixture['cargoGroups']);
        $ship->changeDoors($doors);

        return $ship;
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'];
    }

    /**
     * @return list<DoorSide>
     */
    public static function officialDoors(string $key): array
    {
        return array_map(DoorSide::from(...), self::all()[$key]['doors']);
    }

    public static function volume(string $key): int
    {
        $grids = array_merge(...array_map(static fn (array $group): array => $group['grids'], self::all()[$key]['cargoGroups']));

        return array_sum(array_map(static fn (array $grid): int => $grid['width'] * $grid['height'] * $grid['length'], $grids));
    }

    /**
     * @return array<string, array{label: string, manufacturer: string, name: string, doors: list<string>, cargoGroups: list<array<string, mixed>>}>
     */
    private static function all(): array
    {
        return self::$ships ??= json_decode((string) file_get_contents(__DIR__.'/../Fixtures/ships.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
