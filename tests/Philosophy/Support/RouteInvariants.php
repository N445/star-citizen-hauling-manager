<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\Tour;
use App\Service\Route\Route;
use App\Service\Route\Stop;
use PHPUnit\Framework\Assert;

final readonly class RouteInvariants
{
    private RouteTrace $trace;

    /** @var list<Mission> */
    private array $missions;

    public function __construct(
        private Tour $tour,
        private Route $route,
        private string $case,
    ) {
        $this->trace = new RouteTrace($route);
        $this->missions = $tour->missions()->getValues();
    }

    public function assertCommonPhilosophy(): void
    {
        $this->assertEveryPickupVisitedOnce();
        $this->assertEveryDeliveryDoneOnce();
        $this->assertDeliveriesFollowAllPickups();
        $this->assertCapacityNeverExceeded();
        $this->assertNoConsecutiveSameLocation();
        $this->assertStartsAtStartLocation();
        $this->assertNoUselessStop();
        $this->assertStopCountAtLeastDistinctLocations();
    }

    public function assertAtMostOneMissionInProgress(): void
    {
        foreach (array_keys($this->route->stops) as $step) {
            $inProgress = $this->trace->missionsInProgressAfter($step, $this->missions);
            Assert::assertLessThanOrEqual(1, \count($inProgress), $this->message(sprintf('« mission par mission » : %d missions entamées et non terminées après l\'arrêt %d', \count($inProgress), $step)));
        }
    }

    public function stopCount(): int
    {
        return \count($this->route->stops);
    }

    /**
     * @return list<string>
     */
    public function signature(): array
    {
        return array_map(static fn (Stop $stop): string => sprintf(
            '%s | livre %s | ramasse %s',
            $stop->location,
            implode(',', array_map(static fn (Delivery $delivery): string => (string) $delivery->id(), $stop->deliveries)),
            implode(',', array_map(static fn (Mission $mission): string => (string) $mission->id(), $stop->pickups)),
        ), $this->route->stops);
    }

    private function assertEveryPickupVisitedOnce(): void
    {
        foreach ($this->missions as $mission) {
            $expected = array_values(array_unique($mission->pickups()));
            $visited = $this->trace->pickupLocationsOf($mission);
            sort($expected);
            sort($visited);
            Assert::assertSame($expected, $visited, $this->message(sprintf('tout est fait : la mission %d doit être ramassée exactement une fois à chacun de ses lieux', $mission->id())));
        }
    }

    private function assertEveryDeliveryDoneOnce(): void
    {
        foreach ($this->deliveries() as $delivery) {
            $steps = $this->trace->deliveryStepsOf($delivery);
            Assert::assertCount(1, $steps, $this->message(sprintf('tout est fait : la livraison %d doit être livrée exactement une fois', $delivery->id())));
            Assert::assertSame($delivery->dropoff(), $this->route->stops[$steps[0]]->location, $this->message(sprintf('la livraison %d est livrée ailleurs qu\'à sa destination', $delivery->id())));
        }
    }

    private function assertDeliveriesFollowAllPickups(): void
    {
        foreach ($this->deliveries() as $delivery) {
            $lastPickup = max([-1, ...$this->trace->pickupStepsOf($delivery->mission())]);
            Assert::assertGreaterThanOrEqual($lastPickup, $this->trace->deliveryStepOf($delivery), $this->message(sprintf('précédence : la livraison %d a lieu avant le dernier ramassage de sa mission', $delivery->id())));
        }
    }

    private function assertCapacityNeverExceeded(): void
    {
        foreach (array_keys($this->route->stops) as $step) {
            $load = $this->trace->loadAfter($step, $this->missions);
            Assert::assertLessThanOrEqual($this->tour->capacity(), $load, $this->message(sprintf('capacité : %d SCU à bord après l\'arrêt %d pour %d SCU de capacité', $load, $step, $this->tour->capacity())));
        }
    }

    private function assertNoConsecutiveSameLocation(): void
    {
        $locations = array_map(static fn (Stop $stop): string => $stop->location, $this->route->stops);

        foreach (array_keys(\array_slice($locations, 1)) as $index) {
            Assert::assertNotSame($locations[$index], $locations[$index + 1], $this->message(sprintf('deux arrêts consécutifs à « %s » (arrêts %d et %d)', $locations[$index], $index, $index + 1)));
        }
    }

    private function assertStartsAtStartLocation(): void
    {
        $start = $this->tour->startLocation();
        Assert::assertSame($start, $this->route->start, $this->message('le parcours doit annoncer le point de départ de la tournée'));

        if (null === $start) {
            return;
        }

        Assert::assertSame($start, $this->route->stops[0]->location ?? null, $this->message('le premier arrêt doit être le point de départ'));
    }

    private function assertNoUselessStop(): void
    {
        $stops = null === $this->tour->startLocation() ? $this->route->stops : \array_slice($this->route->stops, 1);

        foreach ($stops as $stop) {
            Assert::assertFalse($stop->isEmpty(), $this->message(sprintf('arrêt inutile à « %s » : rien n\'y est ramassé ni livré', $stop->location)));
        }
    }

    private function assertStopCountAtLeastDistinctLocations(): void
    {
        Assert::assertGreaterThanOrEqual(\count($this->locationsToServe()), $this->stopCount(), $this->message('borne basse : moins d\'arrêts que de lieux distincts à desservir'));
    }

    /**
     * @return list<string>
     */
    private function locationsToServe(): array
    {
        $pickups = array_merge(...array_map(static fn (Mission $mission): array => $mission->pickups(), $this->missions));
        $dropoffs = array_map(static fn (Delivery $delivery): string => $delivery->dropoff(), $this->deliveries());

        return array_values(array_unique(array_filter([...$pickups, ...$dropoffs, $this->tour->startLocation()])));
    }

    /**
     * @return list<Delivery>
     */
    private function deliveries(): array
    {
        return array_merge(...array_map(static fn (Mission $mission): array => $mission->deliveries()->getValues(), $this->missions));
    }

    private function message(string $principle): string
    {
        return sprintf("%s\nCas : %s\nParcours :\n  %s", $principle, $this->case, implode("\n  ", $this->signature()));
    }
}
