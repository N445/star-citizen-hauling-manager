<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class RouteOptimizer
{
    private const MAX_PASSES = 100;

    /**
     * @param list<Mission> $missions
     */
    public function optimize(array $missions, Ship $ship, Location $start): Route
    {
        $transfers = $this->transfers($missions, $ship);
        if ([] === $transfers) {
            return new Route([], 0);
        }

        $best = $this->nearestNeighbour($transfers, $ship->capacity(), $start);

        for ($pass = 0; $pass < self::MAX_PASSES; ++$pass) {
            $improved = $this->improve($best, $transfers, $ship->capacity(), $start);
            if (null === $improved) {
                break;
            }
            $best = $improved;
        }

        return $best;
    }

    /**
     * @param list<Mission> $missions
     *
     * @return list<CrateTransfer>
     */
    private function transfers(array $missions, Ship $ship): array
    {
        $transfers = [];
        foreach ($missions as $mission) {
            $mission->validate();
            $maxCrate = min($ship->grid->maxCrateSize(), $mission->maxContainerSize);
            foreach ($mission->lots as $lot) {
                if (null !== $lot->dropoff && $lot->pickup->id === $lot->dropoff->id) {
                    throw new \InvalidArgumentException(sprintf('Lot "%s" has the same pickup and dropoff.', $lot->id));
                }
                foreach ($lot->withCrates($maxCrate)->crates as $crate) {
                    if ($crate->sizeScu > $mission->maxContainerSize) {
                        throw new \InvalidArgumentException(sprintf('La caisse %s (%d SCU) dépasse la taille maximale de la mission "%s" (%d SCU).', $crate->id, $crate->sizeScu, $mission->label, $mission->maxContainerSize));
                    }
                    if (!$ship->grid->accepts($crate)) {
                        throw new \DomainException(sprintf('Aucun emplacement pour une caisse de %d SCU dans %s', $crate->sizeScu, $ship->name));
                    }
                    $transfers[] = new CrateTransfer($crate, $lot->pickup, $lot->dropoff);
                }
            }
        }

        return $transfers;
    }

    /**
     * @param list<CrateTransfer> $transfers
     */
    private function nearestNeighbour(array $transfers, int $capacity, Location $start): Route
    {
        $simulation = new RouteSimulation($transfers, $capacity, $start);
        $simulation->visit($start);

        while (!$simulation->isComplete()) {
            $candidates = $simulation->candidates();
            if ([] === $candidates) {
                throw new \DomainException('Missions cannot be routed with this ship.');
            }
            $current = $simulation->current();
            usort($candidates, static fn (Location $a, Location $b): int => LocationGraph::cost($current, $a) <=> LocationGraph::cost($current, $b));
            $simulation->visit($candidates[0]);
        }

        return $simulation->route();
    }

    /**
     * @param list<CrateTransfer> $transfers
     */
    private function improve(Route $route, array $transfers, int $capacity, Location $start): ?Route
    {
        $sequence = array_map(static fn (Stop $stop): Location => $stop->location, $route->stops);

        foreach ($this->neighbours($sequence) as $candidate) {
            $simulation = new RouteSimulation($transfers, $capacity, $start);
            foreach ($candidate as $location) {
                $simulation->visit($location);
            }
            if (!$simulation->isComplete()) {
                continue;
            }
            $result = $simulation->route();
            if ([$result->cost, count($result->stops)] < [$route->cost, count($route->stops)]) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Removals, relocations and segment reversals (2-opt) of a visit sequence.
     *
     * @param list<Location> $sequence
     *
     * @return \Generator<list<Location>>
     */
    private function neighbours(array $sequence): \Generator
    {
        $count = count($sequence);
        for ($i = 0; $i < $count; ++$i) {
            $without = $sequence;
            array_splice($without, $i, 1);
            yield $without;

            for ($j = 0; $j < $count; ++$j) {
                if ($i === $j) {
                    continue;
                }
                $moved = $without;
                array_splice($moved, $j, 0, [$sequence[$i]]);
                yield $moved;
            }

            for ($j = $i + 1; $j < $count; ++$j) {
                $reversed = $sequence;
                array_splice($reversed, $i, $j - $i + 1, array_reverse(array_slice($sequence, $i, $j - $i + 1)));
                yield $reversed;
            }
        }
    }
}
