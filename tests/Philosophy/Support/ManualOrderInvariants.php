<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\Tour;
use App\Service\Route\Route;
use App\Service\Route\RouteMode;
use App\Service\Route\Stop;
use PHPUnit\Framework\Assert;

final readonly class ManualOrderInvariants
{
    private RouteTrace $trace;

    /** @var list<Mission> */
    private array $missions;

    public function __construct(
        private Tour $tour,
        private Route $route,
        private RouteMode $mode,
        private string $case,
    ) {
        $this->trace = new RouteTrace($route);
        $this->missions = $tour->missions()->getValues();
    }

    /**
     * @param list<string> $manualOrder
     */
    public function assertManualOrderFollowed(array $manualOrder): void
    {
        $step = null === $this->tour->startLocation() ? 0 : 1;

        foreach ($manualOrder as $location) {
            $followed = ($this->route->stops[$step]->location ?? null) === $location;
            Assert::assertTrue($followed || !$this->isUsefulAfter($location, $step - 1), $this->message(sprintf('ordre manuel : « %s » est sauté ou déplacé alors qu\'il y avait quelque chose à y faire (après l\'arrêt %d)', $location, $step - 1), $manualOrder));
            $step += $followed ? 1 : 0;
        }
    }

    private function isUsefulAfter(string $location, int $step): bool
    {
        return [] !== array_filter($this->missions, fn (Mission $mission): bool => $this->canDeliverAt($mission, $location, $step) || $this->canPickUpAt($mission, $location, $step));
    }

    private function canDeliverAt(Mission $mission, string $location, int $step): bool
    {
        $pending = array_filter($mission->deliveries()->getValues(), fn (Delivery $delivery): bool => $delivery->dropoff() === $location && $this->trace->deliveryStepOf($delivery) > $step);

        return [] !== $pending && $this->isFullyPickedUpAfter($mission, $step);
    }

    private function canPickUpAt(Mission $mission, string $location, int $step): bool
    {
        $pickedUpThere = \in_array($location, $this->pickedUpLocationsAfter($mission, $step), true);

        if ($pickedUpThere || !\in_array($location, $mission->pickups(), true)) {
            return false;
        }

        return $this->trace->firstPickupStepOf($mission) <= $step || $this->mayStart($mission, $step);
    }

    private function mayStart(Mission $mission, int $step): bool
    {
        $inProgress = $this->trace->missionsInProgressAfter($step, $this->missions);

        if (RouteMode::MissionFocus === $this->mode) {
            return [] === $inProgress;
        }

        return $this->trace->loadAfter($step, $this->missions) + $mission->effectiveQuantity() <= $this->tour->capacity();
    }

    private function isFullyPickedUpAfter(Mission $mission, int $step): bool
    {
        return \count($this->pickedUpLocationsAfter($mission, $step)) === \count(array_unique($mission->pickups()));
    }

    /**
     * @return list<string>
     */
    private function pickedUpLocationsAfter(Mission $mission, int $step): array
    {
        $steps = $this->trace->pickupStepsOf($mission);
        $locations = $this->trace->pickupLocationsOf($mission);

        return array_values(array_filter($locations, static fn (string $location, int $index): bool => $steps[$index] <= $step, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param list<string> $manualOrder
     */
    private function message(string $principle, array $manualOrder): string
    {
        $locations = array_map(static fn (Stop $stop): string => $stop->location, $this->route->stops);

        return sprintf("%s\nCas : %s\nOrdre manuel : %s\nParcours : %s", $principle, $this->case, implode(' → ', $manualOrder), implode(' → ', $locations));
    }
}
