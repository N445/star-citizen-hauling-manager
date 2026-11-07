<?php

declare(strict_types=1);

namespace App\Service\Cargo;

/**
 * Règles d'un placement manuel : les caisses déplacées doivent tenir dans la soute à côté des caisses qui ne bougent
 * pas, et aucune de celles-ci ne doit perdre son appui.
 */
final readonly class PlacementRules
{
    /**
     * @param list<array<string, mixed>> $groups grille officielle de sc-cargo.space
     * @param list<array{size: int, x: float, y: float, z: float, width: int, height: int, length: int}> $fixed
     * @param list<array{size: int, x: float, y: float, z: float, width: int, height: int, length: int}> $moved
     *
     * @throws PlacementException
     */
    public static function assertLayout(array $groups, array $fixed, array $moved): void
    {
        $space = CargoSpace::fromGroups($groups, []);
        array_walk($fixed, static fn (array $placement) => $space->block($placement));
        usort($moved, static fn (array $first, array $second): int => $first['y'] <=> $second['y']);

        foreach ($moved as $placement) {
            $space->spotFor($placement, $placement['size']);
            $space->block($placement);
        }

        if ([] !== array_filter($fixed, static fn (array $placement): bool => !$space->isSupported($placement))) {
            throw new PlacementException('cargo.edit.error.supports');
        }
    }
}
