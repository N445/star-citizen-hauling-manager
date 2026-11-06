<?php

declare(strict_types=1);

namespace App\Service\Cargo;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\Tour;
use App\Service\Route\Route;
use App\Service\Route\Stop;

/**
 * Rejoue le parcours arrêt par arrêt : décharge, puis charge chaque caisse à une profondeur qui suit
 * l'ordre des livraisons à venir (la prochaine → côté porte, la dernière → au fond).
 */
final class CargoPlanner
{
    private CargoSpace $space;

    /** @var array<string, PlacedCrate> */
    private array $aboard = [];

    /** @var list<PlacedCrate> */
    private array $placed = [];

    /** @var array<int, true> */
    private array $loadedMissions = [];

    /** @var array<int, int> */
    private array $dropSteps = [];

    private int $unplacedCount = 0;


    public function plan(Tour $tour, Route $route): CargoPlan
    {
        if (null === $tour->ship()) {
            return CargoPlan::empty();
        }

        $this->space = CargoSpace::fromGroups($tour->ship()->cargoGroups(), $tour->doorSides());
        $steps = $route->stops;
        $this->dropSteps = $this->dropSteps($steps);

        foreach ($steps as $stepIndex => $stop) {
            $this->unload($stop);
            $this->load($stop, $stepIndex);
        }

        return new CargoPlan($this->placed, $this->currentStep($steps), $this->unplacedCount);
    }

    /**
     * @param list<Stop> $steps
     *
     * @return array<int, int>
     */
    private function dropSteps(array $steps): array
    {
        $dropSteps = [];

        foreach ($steps as $stepIndex => $stop) {
            $dropSteps += array_fill_keys(array_map(static fn (Delivery $delivery): int => $delivery->id(), $stop->deliveries), $stepIndex);
        }

        return $dropSteps;
    }

    private function unload(Stop $stop): void
    {
        $deliveryIds = array_map(static fn (Delivery $delivery): int => $delivery->id(), $stop->deliveries);

        foreach (array_filter($this->aboard, static fn (PlacedCrate $crate): bool => \in_array($crate->delivery->id(), $deliveryIds, true)) as $crateId => $crate) {
            $crate->spot->bay->free($crate->spot);
            unset($this->aboard[$crateId]);
        }
    }

    private function load(Stop $stop, int $stepIndex): void
    {
        $missions = array_filter($stop->pickups, fn (Mission $mission): bool => !isset($this->loadedMissions[$mission->id()]));
        $deliveries = array_merge(...array_map(static fn (Mission $mission): array => $mission->deliveries()->getValues(), array_values($missions)));
        usort($deliveries, fn (Delivery $first, Delivery $second): int => $this->dropStepOf($second) <=> $this->dropStepOf($first));

        foreach ($missions as $mission) {
            $this->loadedMissions[$mission->id()] = true;
        }

        $frozenMissionIds = array_map(static fn (Mission $mission): int => $mission->id(), array_filter($missions, fn (Mission $mission): bool => $this->loadFrozen($mission, $stop->location, $stepIndex)));

        foreach (array_filter($deliveries, static fn (Delivery $delivery): bool => !\in_array($delivery->mission()->id(), $frozenMissionIds, true)) as $delivery) {
            $this->loadDelivery($delivery, $stop->location, $stepIndex);
        }
    }

    /**
     * Caisses déjà chargées par le joueur : elles restent où elles ont été posées, si la soute le permet encore.
     */
    private function loadFrozen(Mission $mission, string $location, int $stepIndex): bool
    {
        $placement = $mission->frozenCargo();
        $deliveries = [];

        foreach ($mission->deliveries() as $delivery) {
            $deliveries[$delivery->id()] = $delivery;
        }

        $spots = array_map(fn (array $crate): ?Spot => isset($deliveries[$crate['delivery']]) ? $this->space->availableSpot($crate) : null, $placement ?? []);

        if (null === $placement || \in_array(null, $spots, true)) {
            return false;
        }

        foreach ($placement as $index => $crate) {
            $delivery = $deliveries[$crate['delivery']];
            $this->place(new PlacedCrate(sprintf('%d-%d', $delivery->id(), \count($this->placed)), $delivery, $crate['size'], $spots[$index], $location, $stepIndex, $this->dropSteps[$delivery->id()] ?? null));
        }

        return true;
    }

    private function loadDelivery(Delivery $delivery, string $location, int $stepIndex): void
    {
        $pending = CrateSize::split($delivery->quantity(), $this->space->largestCrate());

        while ([] !== $pending) {
            $pending = [...$this->placeOrSplit(array_shift($pending), $delivery, $location, $stepIndex), ...$pending];
        }
    }

    /**
     * @return list<int> les tailles restant à placer quand la caisse a dû être découpée
     */
    private function placeOrSplit(int $size, Delivery $delivery, string $location, int $stepIndex): array
    {
        $targetDepth = $this->targetDepth($delivery, $stepIndex);
        $deliveredEarlier = $this->spotsDeliveredBefore($delivery);
        $sameDestination = $this->spotsGoingTo($delivery->dropoff());
        $spot = $this->space->bestSpotFor($size, $targetDepth, $deliveredEarlier, $sameDestination);

        if (null !== $spot && $this->smallerCratesAvoidStacking($size, $targetDepth, $deliveredEarlier, $sameDestination)) {
            return CrateSize::halve($size);
        }

        if (null !== $spot) {
            $this->place(new PlacedCrate(sprintf('%d-%d', $delivery->id(), \count($this->placed)), $delivery, $size, $spot, $location, $stepIndex, $this->dropSteps[$delivery->id()] ?? null));

            return [];
        }

        if (1 === $size) {
            ++$this->unplacedCount;

            return [];
        }

        return CrateSize::halve($size);
    }

    private function place(PlacedCrate $crate): void
    {
        $crate = $crate->withStacking($this->space->isStackedOnAny($crate->spot, $this->spotsDeliveredBefore($crate->delivery)));
        $crate->spot->bay->occupy($crate->spot);
        $this->aboard[$crate->id] = $crate;
        $this->placed[] = $crate;
    }

    /**
     * Rang de la livraison parmi celles encore à venir : la prochaine contre la porte (0), la dernière au fond (1).
     */
    private function targetDepth(Delivery $delivery, int $stepIndex): float
    {
        $dropStep = $this->dropSteps[$delivery->id()] ?? null;
        $upcoming = array_filter($this->dropSteps, static fn (int $step): bool => $step > $stepIndex);

        if (null === $dropStep || [] === $upcoming) {
            return 1.0;
        }

        $nearest = min($upcoming);

        return ($dropStep - $nearest) / max(1, max($upcoming) - $nearest);
    }

    /**
     * Une caisse qu'on devrait poser sur une livraison antérieure est découpée si des caisses plus petites y échappent.
     *
     * @param list<Spot> $deliveredEarlier
     * @param list<Spot> $sameDestination
     */
    private function smallerCratesAvoidStacking(int $size, float $targetDepth, array $deliveredEarlier, array $sameDestination): bool
    {
        if (1 === $size || !$this->space->stacksOnEarlierDelivery($size, $targetDepth, $deliveredEarlier, $sameDestination)) {
            return false;
        }

        $half = CrateSize::halve($size)[0];

        return null !== $this->space->bestSpotFor($half, $targetDepth, $deliveredEarlier, $sameDestination)
            && !$this->space->stacksOnEarlierDelivery($half, $targetDepth, $deliveredEarlier, $sameDestination);
    }

    /**
     * @return list<Spot>
     */
    private function spotsGoingTo(string $dropoff): array
    {
        $sameDestination = array_filter($this->aboard, static fn (PlacedCrate $crate): bool => $crate->delivery->dropoff() === $dropoff);

        return array_values(array_map(static fn (PlacedCrate $crate): Spot => $crate->spot, $sameDestination));
    }

    /**
     * @return list<Spot>
     */
    private function spotsDeliveredBefore(Delivery $delivery): array
    {
        $dropStep = $this->dropStepOf($delivery);
        $earlier = array_filter($this->aboard, fn (PlacedCrate $crate): bool => ($crate->dropStep ?? PHP_INT_MAX) < $dropStep);

        return array_values(array_map(static fn (PlacedCrate $crate): Spot => $crate->spot, $earlier));
    }

    private function dropStepOf(Delivery $delivery): int
    {
        return $this->dropSteps[$delivery->id()] ?? PHP_INT_MAX;
    }

    /**
     * @param list<Stop> $steps
     */
    private function currentStep(array $steps): int
    {
        foreach ($steps as $stepIndex => $stop) {
            if (!$stop->isDone() && !$stop->isEmpty()) {
                return $stepIndex;
            }
        }

        return \count($steps);
    }
}
