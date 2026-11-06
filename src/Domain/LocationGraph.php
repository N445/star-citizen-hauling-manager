<?php

declare(strict_types=1);

namespace App\Domain;

final class LocationGraph
{
    public static function tier(Location $a, Location $b): TravelTier
    {
        return match (true) {
            $a->system !== $b->system => TravelTier::Interstellar,
            $a->planet !== $b->planet => TravelTier::Stellar,
            self::sameLocalArea($a, $b) => TravelTier::Local,
            default => TravelTier::Planetary,
        };
    }

    public static function cost(Location $a, Location $b): int
    {
        return self::tier($a, $b)->cost();
    }

    /** Surface and orbital places of a planet are equivalent; a given moon or Lagrange point is its own area. */
    private static function sameLocalArea(Location $a, Location $b): bool
    {
        $aPlanetary = self::isPlanetaryArea($a);
        $bPlanetary = self::isPlanetaryArea($b);
        if ($aPlanetary || $bPlanetary) {
            return $aPlanetary && $bPlanetary;
        }

        return $a->type === $b->type && $a->body === $b->body;
    }

    private static function isPlanetaryArea(Location $l): bool
    {
        return LocationType::Surface === $l->type || LocationType::Orbital === $l->type;
    }
}
