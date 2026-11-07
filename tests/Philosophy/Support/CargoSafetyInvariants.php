<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\Tour;
use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\PlacedCrate;
use App\Service\Route\Route;
use PHPUnit\Framework\Assert;

final readonly class CargoSafetyInvariants
{
    private const array CRATE_DIMENSIONS = [
        32 => [2, 8, 2],
        24 => [2, 6, 2],
        16 => [2, 4, 2],
        8 => [2, 2, 2],
        4 => [2, 2, 1],
        2 => [1, 2, 1],
        1 => [1, 1, 1],
    ];

    private CargoTimeline $timeline;

    private CargoRoom $room;

    public function __construct(
        private Tour $tour,
        private Route $route,
        private CargoPlan $plan,
        private string $case,
    ) {
        $this->timeline = new CargoTimeline($plan);
        $this->room = CargoRoom::of($tour->ship());
    }

    public function assertAll(): void
    {
        $this->assertCratesFollowTheRoute();
        $this->assertNoOverlapAboard();
        $this->assertInsideTheirGrid();
        $this->assertSupportedWhenLoaded();
        $this->assertStandardSizesAllowedByGrid();
        $this->assertQuantitiesAccountedFor();
        $this->assertStackingOnEarlierDeliveryIsFlagged();
    }

    private function assertCratesFollowTheRoute(): void
    {
        $trace = new RouteTrace($this->route);

        foreach ($this->timeline->crates() as $crate) {
            Assert::assertSame($trace->firstPickupStepOf($crate->delivery->mission()), $crate->pickupStep, $this->message('la caisse est chargée ailleurs qu\'au premier ramassage de sa mission : '.(new CrateBox($crate))->describe()));
            Assert::assertSame($trace->deliveryStepOf($crate->delivery), $crate->dropStep, $this->message('la caisse n\'est pas déchargée à l\'arrêt de sa livraison : '.(new CrateBox($crate))->describe()));
        }
    }

    private function assertNoOverlapAboard(): void
    {
        foreach ($this->timeline->steps() as $step) {
            $this->assertNoOverlapAmong($this->timeline->aboardAt($step), $step);
        }
    }

    /**
     * @param list<PlacedCrate> $aboard
     */
    private function assertNoOverlapAmong(array $aboard, int $step): void
    {
        foreach ($aboard as $index => $crate) {
            foreach (\array_slice($aboard, $index + 1) as $other) {
                Assert::assertFalse((new CrateBox($crate))->overlaps(new CrateBox($other)), $this->message(sprintf('chevauchement à l\'étape %d : %s et %s', $step, (new CrateBox($crate))->describe(), (new CrateBox($other))->describe())));
            }
        }
    }

    private function assertInsideTheirGrid(): void
    {
        foreach ($this->timeline->crates() as $crate) {
            $spot = $crate->spot;
            $inside = $spot->offsetX >= 0 && $spot->offsetY >= 0 && $spot->offsetZ >= 0
                && $spot->offsetX + $spot->width <= $spot->bay->width
                && $spot->offsetY + $spot->height <= $spot->bay->height
                && $spot->offsetZ + $spot->length <= $spot->bay->length;
            Assert::assertTrue($inside, $this->message('caisse hors de sa grille : '.(new CrateBox($crate))->describe()));
            Assert::assertNotNull($this->room->gridOf($spot), $this->message('caisse posée dans une grille qui n\'existe pas dans le vaisseau : '.(new CrateBox($crate))->describe()));
        }
    }

    private function assertSupportedWhenLoaded(): void
    {
        foreach ($this->timeline->crates() as $index => $crate) {
            $box = new CrateBox($crate);
            $onFloor = abs($box->start('y') - $crate->spot->bay->y) < 0.001;
            $support = array_sum(array_map(static fn (PlacedCrate $below): float => $box->supportAreaFrom(new CrateBox($below)), $this->timeline->aboardWhenLoading($index)));
            Assert::assertTrue($onFloor || abs($support - $box->footprint()) < 0.001, $this->message(sprintf('caisse en l\'air au chargement (%s SCU² soutenus sur %s) : %s', $support, $box->footprint(), $box->describe())));
        }
    }

    private function assertStandardSizesAllowedByGrid(): void
    {
        foreach ($this->timeline->crates() as $crate) {
            $description = (new CrateBox($crate))->describe();
            Assert::assertArrayHasKey($crate->size, self::CRATE_DIMENSIONS, $this->message('taille de caisse non standard : '.$description));
            [$width, $length, $height] = self::CRATE_DIMENSIONS[$crate->size];
            $footprint = [$crate->spot->width, $crate->spot->length];
            sort($footprint);
            Assert::assertSame([[$width, $length], $height], [$footprint, $crate->spot->height], $this->message('dimensions qui ne correspondent pas à la taille : '.$description));
            $grid = $this->room->gridOf($crate->spot) ?? ['minSize' => 1, 'maxSize' => 32];
            Assert::assertTrue($crate->size >= $grid['minSize'] && $crate->size <= $grid['maxSize'], $this->message(sprintf('taille hors des limites de la grille (%d..%d) : %s', $grid['minSize'], $grid['maxSize'], $description)));
        }
    }

    private function assertQuantitiesAccountedFor(): void
    {
        $missing = 0;

        foreach ($this->deliveries() as $delivery) {
            $placed = $this->placedQuantityOf($delivery);
            Assert::assertLessThanOrEqual($delivery->effectiveQuantity(), $placed, $this->message(sprintf('la livraison %d a plus de SCU en caisses (%d) que sa quantité (%d)', $delivery->id(), $placed, $delivery->effectiveQuantity())));
            $missing += $delivery->effectiveQuantity() - $placed;
        }

        Assert::assertSame($missing, $this->plan->unplacedCount, $this->message(sprintf('%d SCU manquent dans les caisses mais %d sont signalés sans place', $missing, $this->plan->unplacedCount)));
    }

    private function assertStackingOnEarlierDeliveryIsFlagged(): void
    {
        foreach ($this->timeline->crates() as $index => $crate) {
            $deliveredEarlierBelow = array_filter($this->timeline->aboardWhenLoading($index), static fn (PlacedCrate $below): bool => CargoTimeline::dropStepOf($below) < CargoTimeline::dropStepOf($crate) && (new CrateBox($crate))->restsOn(new CrateBox($below)));
            Assert::assertTrue([] === $deliveredEarlierBelow || $crate->stackedOnEarlierDelivery, $this->message('caisse posée sur une livraison antérieure sans être signalée : '.(new CrateBox($crate))->describe()));
        }

        $flagged = array_filter($this->timeline->crates(), static fn (PlacedCrate $crate): bool => $crate->stackedOnEarlierDelivery);
        Assert::assertSame(\count($flagged), $this->plan->stackedCount(), $this->message('stackedCount() ne compte pas les caisses signalées'));
    }

    private function placedQuantityOf(Delivery $delivery): int
    {
        $crates = array_filter($this->timeline->crates(), static fn (PlacedCrate $crate): bool => $crate->delivery === $delivery);

        return array_sum(array_map(static fn (PlacedCrate $crate): int => $crate->size, $crates));
    }

    /**
     * @return list<Delivery>
     */
    private function deliveries(): array
    {
        return array_merge(...array_map(static fn (Mission $mission): array => $mission->deliveries()->getValues(), $this->tour->missions()->getValues()));
    }

    private function message(string $principle): string
    {
        return sprintf("Soute · %s\nCas : %s", $principle, $this->case);
    }
}
