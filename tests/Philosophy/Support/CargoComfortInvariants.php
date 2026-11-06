<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\DoorSide;
use App\Entity\Tour;
use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\PlacedCrate;
use App\Service\Route\Route;
use PHPUnit\Framework\Assert;

final readonly class CargoComfortInvariants
{
    private CargoTimeline $timeline;

    private CargoRoom $room;

    private CargoLoadProfile $profile;

    public function __construct(
        private Tour $tour,
        Route $route,
        private CargoPlan $plan,
        private string $case,
    ) {
        $this->timeline = new CargoTimeline($plan);
        $this->room = CargoRoom::of($tour->ship());
        $this->profile = new CargoLoadProfile($tour, $route);
    }

    public function assertEverythingPlaced(): void
    {
        Assert::assertSame(0, $this->plan->unplacedCount, $this->message(sprintf('%d SCU sans place alors que la charge ne dépasse pas la moitié du volume utile (%s)', $this->plan->unplacedCount, $this->profile->describe())));
    }

    public function assertNothingStacked(): void
    {
        $stacked = array_filter($this->plan->crates, static fn (PlacedCrate $crate): bool => $crate->stackedOnEarlierDelivery);
        $descriptions = array_map(static fn (PlacedCrate $crate): string => (new CrateBox($crate))->describe(), $stacked);
        Assert::assertSame([], array_values($descriptions), $this->message('soute large ('.$this->profile->describe().') : aucune caisse ne devrait être posée sur une livraison antérieure'));
    }

    public function assertEarlierDeliveriesCloserToTheDoor(): void
    {
        $door = $this->tour->ship()->doorSides()[0];

        foreach ($this->timeline->steps() as $step) {
            $this->assertAccessOrderAt($step, $door);
        }
    }

    public function assertSameDestinationFormsOneBlock(): void
    {
        foreach ($this->timeline->steps() as $step) {
            foreach ($this->freshGroupsLoadedAtPerGrid($step) as $dropoff => $crates) {
                Assert::assertTrue($this->isConnected($crates), $this->message(sprintf('groupement : les caisses pour « %s » chargées à l\'étape %d forment plusieurs blocs dans une même grille : %s', $dropoff, $step, implode(' ; ', array_map(static fn (PlacedCrate $crate): string => (new CrateBox($crate))->describe(), $crates)))));
            }
        }
    }

    private function assertAccessOrderAt(int $step, DoorSide $door): void
    {
        $groups = $this->freshGroupsLoadedAt($step);

        foreach ($groups as $earlierDropoff => $earlier) {
            foreach ($groups as $laterDropoff => $later) {
                $this->assertGroupNotBehind($earlier, $later, $door, sprintf('à l\'étape %d, « %s » (livrée à l\'étape %d) / « %s » (livrée à l\'étape %d)', $step, $earlierDropoff, CargoTimeline::dropStepOf($earlier[0]), $laterDropoff, CargoTimeline::dropStepOf($later[0])));
            }
        }
    }

    /**
     * @param list<PlacedCrate> $earlier
     * @param list<PlacedCrate> $later
     */
    private function assertGroupNotBehind(array $earlier, array $later, DoorSide $door, string $context): void
    {
        if (CargoTimeline::dropStepOf($earlier[0]) >= CargoTimeline::dropStepOf($later[0])) {
            return;
        }

        $earlierReach = $this->nearestFaceDistance($earlier, $door);
        $laterReach = $this->nearestFaceDistance($later, $door);
        Assert::assertLessThanOrEqual($laterReach + 0.001, $earlierReach, $this->message(sprintf('ordre d\'accès (porte %s) : %s, la livraison la plus proche est atteignable à %.2f de la porte, la suivante à %.2f', mb_strtolower($door->label()), $context, $earlierReach, $laterReach)));

        foreach ($earlier as $crate) {
            $blockers = array_filter($later, static fn (PlacedCrate $blocker): bool => (new CrateBox($blocker))->isBetweenDoorAnd(new CrateBox($crate), $door));
            Assert::assertSame([], array_values(array_map(static fn (PlacedCrate $blocker): string => (new CrateBox($blocker))->describe(), $blockers)), $this->message(sprintf('ordre d\'accès (porte %s) : %s, %s est bloquée par une caisse livrée plus tard', mb_strtolower($door->label()), $context, (new CrateBox($crate))->describe())));
        }
    }

    /**
     * @param list<PlacedCrate> $crates
     */
    private function nearestFaceDistance(array $crates, DoorSide $door): float
    {
        $plane = $this->room->doorPlane($door);
        $axis = $door->axis();

        return min(array_map(static fn (PlacedCrate $crate): float => $door->isAtMaximum() ? $plane - (new CrateBox($crate))->end($axis) : (new CrateBox($crate))->start($axis) - $plane, $crates));
    }

    /**
     * @return array<string, list<PlacedCrate>> caisses chargées à l'étape, par destination encore absente de la soute
     */
    private function freshGroupsLoadedAt(int $step): array
    {
        $alreadyAboard = $this->timeline->destinationsAlreadyAboard($step);
        $groups = [];

        foreach ($this->timeline->loadedAt($step) as $crate) {
            $groups[$crate->delivery->dropoff()][] = $crate;
        }

        return array_diff_key($groups, array_flip($alreadyAboard));
    }

    /**
     * @return array<string, list<PlacedCrate>>
     */
    private function freshGroupsLoadedAtPerGrid(int $step): array
    {
        $groups = [];

        foreach ($this->freshGroupsLoadedAt($step) as $dropoff => $crates) {
            foreach ($crates as $crate) {
                $groups[sprintf('%s » dans la grille en x=%s y=%s z=%s', $dropoff, $crate->spot->bay->x, $crate->spot->bay->y, $crate->spot->bay->z)][] = $crate;
            }
        }

        return $groups;
    }

    /**
     * @param list<PlacedCrate> $crates
     */
    private function isConnected(array $crates): bool
    {
        $reached = [0];

        for ($cursor = 0; $cursor < \count($reached); ++$cursor) {
            $reached = [...$reached, ...$this->unreachedNeighbours($crates, $reached[$cursor], $reached)];
        }

        return \count($reached) === \count($crates);
    }

    /**
     * @param list<PlacedCrate> $crates
     * @param list<int>         $reached
     *
     * @return list<int>
     */
    private function unreachedNeighbours(array $crates, int $index, array $reached): array
    {
        $box = new CrateBox($crates[$index]);
        $neighbours = array_filter(array_keys($crates), static fn (int $other): bool => !\in_array($other, $reached, true) && $box->touches(new CrateBox($crates[$other])));

        return array_values($neighbours);
    }

    private function message(string $principle): string
    {
        return sprintf("Soute · %s\nCas : %s\n%s", $principle, $this->case, $this->profile->describe());
    }
}
