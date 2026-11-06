<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Replays a sequence of visits: at each location everything aboard destined there is dropped (crates without destination are never dropped and keep their space),
 * then pending crates are loaded while they fit.
 */
final class RouteSimulation
{
    /** @var array<string, CrateTransfer> */
    private array $pending = [];

    /** @var array<string, CrateTransfer> */
    private array $aboard = [];

    private int $load = 0;

    /** @var list<Stop> */
    private array $stops = [];

    private Location $current;

    /**
     * @param list<CrateTransfer> $transfers
     */
    public function __construct(array $transfers, private readonly int $capacity, private readonly Location $start)
    {
        foreach ($transfers as $transfer) {
            $this->pending[$transfer->crate->id] = $transfer;
        }
        $this->current = $start;
    }

    public function visit(Location $location): void
    {
        $drops = [];
        foreach ($this->aboard as $id => $transfer) {
            if ($transfer->dropoff?->id === $location->id) {
                $drops[] = $transfer->crate;
                $this->load -= $transfer->crate->sizeScu;
                unset($this->aboard[$id]);
            }
        }

        $pickups = [];
        foreach ($this->loadable($location) as $transfer) {
            if ($this->load + $transfer->crate->sizeScu > $this->capacity) {
                continue;
            }
            $pickups[] = $transfer->crate;
            $this->load += $transfer->crate->sizeScu;
            if (null !== $transfer->dropoff) {
                $this->aboard[$transfer->crate->id] = $transfer;
            }
            unset($this->pending[$transfer->crate->id]);
        }

        if ([] === $drops && [] === $pickups) {
            return;
        }

        $lastKey = array_key_last($this->stops);
        if (null !== $lastKey && $this->stops[$lastKey]->location->id === $location->id) {
            $last = $this->stops[$lastKey];
            $this->stops[$lastKey] = new Stop($location, [...$last->pickups, ...$pickups], [...$last->drops, ...$drops]);
        } else {
            $this->stops[] = new Stop($location, $pickups, $drops);
        }
        $this->current = $location;
    }

    public function isComplete(): bool
    {
        return [] === $this->pending && [] === $this->aboard;
    }

    public function current(): Location
    {
        return $this->current;
    }

    /**
     * Locations worth visiting next, drop locations first.
     *
     * @return list<Location>
     */
    public function candidates(): array
    {
        $free = $this->capacity - $this->load;
        $candidates = [];
        foreach ($this->aboard as $transfer) {
            $candidates[$transfer->dropoff->id] = $transfer->dropoff;
        }
        foreach ($this->pending as $transfer) {
            if ($transfer->crate->sizeScu <= $free) {
                $candidates[$transfer->pickup->id] ??= $transfer->pickup;
            }
        }

        return array_values($candidates);
    }

    public function route(): Route
    {
        $cost = 0;
        $previous = $this->start;
        $startTier = null;
        $stops = [];
        foreach ($this->stops as $index => $stop) {
            $tier = LocationGraph::tier($previous, $stop->location);
            $cost += $tier->cost();
            if (0 === $index) {
                $startTier = $tier;
            } else {
                $stops[$index - 1] = $stops[$index - 1]->withTierToNext($tier);
            }
            $stops[$index] = $stop;
            $previous = $stop->location;
        }

        return new Route(array_values($stops), $cost, $startTier);
    }

    /**
     * Pending crates at a location: nearest destination first, then bigger first.
     *
     * @return list<CrateTransfer>
     */
    private function loadable(Location $location): array
    {
        $here = array_values(array_filter(
            $this->pending,
            static fn (CrateTransfer $t): bool => $t->pickup->id === $location->id,
        ));
        $cost = static fn (CrateTransfer $t): int => null === $t->dropoff ? PHP_INT_MAX : LocationGraph::cost($location, $t->dropoff);
        usort($here, static fn (CrateTransfer $a, CrateTransfer $b): int => [$cost($a), $b->crate->sizeScu] <=> [$cost($b), $a->crate->sizeScu]);

        return $here;
    }
}
