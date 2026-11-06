<?php

declare(strict_types=1);

namespace App\Domain;

final class ShipCatalog
{
    public const DEFAULT = 'drake-caterpillar';

    /** @var array<string, Ship>|null */
    private static ?array $ships = null;

    /** @return array<string, Ship> indexed by slug, sorted by "manufacturer name" */
    public static function all(): array
    {
        return self::$ships ??= self::load();
    }

    public static function get(string $slug): Ship
    {
        return self::all()[$slug] ?? throw new \InvalidArgumentException(sprintf('Unknown ship "%s".', $slug));
    }

    /** @return array<string, Ship> */
    private static function load(): array
    {
        $rows = json_decode((string) file_get_contents(__DIR__.'/Data/ships.json'), true, 512, JSON_THROW_ON_ERROR);

        $ships = [];
        foreach ($rows as $row) {
            $blocks = array_map(
                static fn (array $b): CargoBlock => new CargoBlock($b['x'], $b['y'], $b['z'], $b['w'], $b['l'], $b['h'], $b['maxSize'] ?? null),
                $row['blocks'],
            );
            $ships[$row['slug']] = new Ship($row['name'], new CargoGrid($blocks), $row['slug'], $row['manufacturer']);
        }
        uasort($ships, static fn (Ship $a, Ship $b): int => strcasecmp($a->label(), $b->label()));

        return $ships;
    }
}
