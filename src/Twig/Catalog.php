<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Crate;
use App\Domain\ShipCatalog;
use App\Domain\StantonLocations;

/** Static lists shared with the browser once per page instead of being re-rendered by every Live request. */
final class Catalog
{
    /** @return array<string, list<array<string, mixed>>> */
    public static function all(): array
    {
        $ships = [];
        foreach (ShipCatalog::all() as $slug => $ship) {
            $ships[] = ['value' => $slug, 'text' => $ship->label(), 'scu' => $ship->capacity()];
        }

        $locations = [];
        foreach (StantonLocations::all() as $id => $location) {
            $locations[] = ['value' => $id, 'text' => $location->name];
        }

        return [
            'types' => [
                ['value' => 'direct', 'text' => 'Direct'],
                ['value' => 'multipleDropOff', 'text' => 'Plusieurs drops'],
                ['value' => 'multiplePickUp', 'text' => 'Plusieurs pickups'],
                ['value' => 'freeShopping', 'text' => 'Achat libre'],
            ],
            'sizes' => array_map(static fn (int $s): array => ['value' => (string) $s, 'text' => $s.' SCU'], Crate::SIZES),
            'ships' => $ships,
            'locations' => $locations,
            'resources' => array_map(static fn (string $r): array => ['value' => $r, 'text' => $r], ResourceCatalog::all()),
        ];
    }

    public static function json(): string
    {
        return json_encode(self::all(), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'multipleDropOff' => 'Plusieurs drops',
            'multiplePickUp' => 'Plusieurs pickups',
            'freeShopping' => 'Achat libre',
            default => 'Direct',
        };
    }

    public static function shipLabel(string $slug): string
    {
        $ships = ShipCatalog::all();

        return isset($ships[$slug]) ? $ships[$slug]->label() : $slug;
    }

    public static function locationName(string $id): string
    {
        $locations = StantonLocations::all();

        return isset($locations[$id]) ? $locations[$id]->name : $id;
    }
}
