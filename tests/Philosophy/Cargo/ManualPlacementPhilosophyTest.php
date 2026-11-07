<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Cargo;

use App\Service\Cargo\CargoEditor;
use App\Service\Cargo\CargoPlanner;
use App\Service\Cargo\PlacedCrate;
use App\Service\Cargo\PlacementException;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\CargoSafetyInvariants;
use App\Tests\Philosophy\Support\RandomTours;
use App\Tests\Philosophy\Support\TourBlueprint;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Principe : un placement manuel accepté ne casse jamais les règles de la soute (rien ne se chevauche, tout est
 * soutenu et dans une grille), le calcul le respecte à chaque recalcul, et « réinitialiser » rend le placement auto.
 */
final class ManualPlacementPhilosophyTest extends TestCase
{
    /**
     * @return iterable<string, array{TourBlueprint}>
     */
    public static function generatedTours(): iterable
    {
        foreach (range(4001, 4040) as $seed) {
            $blueprint = (new RandomTours($seed))->cargoTour();
            yield $blueprint->name() => [$blueprint];
        }
    }

    #[DataProvider('generatedTours')]
    public function testAnAcceptedManualMoveKeepsTheHoldValidAndSticks(TourBlueprint $blueprint): void
    {
        $tour = $blueprint->tour();
        $route = (new RoutePlanner())->plan($tour, RouteMode::FewestStops);
        $editor = new CargoEditor($this->createStub(EntityManagerInterface::class));
        $plan = (new CargoPlanner())->plan($tour, $route);
        $crates = $plan->visibleCrates();

        if ([] === $crates || null === $tour->ship()) {
            $this->addToAssertionCount(1);

            return;
        }

        $crate = $crates[0];
        $moved = $this->firstAcceptedMove($editor, $tour, $route, $plan->indexInMission($crate), $crate);

        if (null === $moved) {
            $this->addToAssertionCount(1);

            return;
        }

        $after = (new CargoPlanner())->plan($tour, $route);
        (new CargoSafetyInvariants($tour, $route, $after, $blueprint->name()))->assertAll();
        $placed = $after->crateAt($crate->delivery->mission()->id(), $plan->indexInMission($crate));
        self::assertNotNull($placed);
        self::assertSame($moved, [$placed->spot->x(), $placed->spot->y(), $placed->spot->z(), $placed->spot->width, $placed->spot->length]);

        $editor->reset($tour, $route);
        self::assertFalse($crate->delivery->mission()->hasManualCargo());
    }

    /**
     * @return array{float, float, float, int, int}|null
     */
    private function firstAcceptedMove(CargoEditor $editor, \App\Entity\Tour $tour, \App\Service\Route\Route $route, int $index, PlacedCrate $crate): ?array
    {
        foreach ($tour->ship()->cargoGroups() as $group) {
            foreach ($group['grids'] as $grid) {
                foreach ([[$crate->spot->width, $crate->spot->length], [$crate->spot->length, $crate->spot->width]] as [$width, $length]) {
                    foreach (range(0, $grid['height'] - $crate->spot->height) as $y) {
                        foreach (range(0, $grid['length'] - $length) as $z) {
                            foreach (range(0, $grid['width'] - $width) as $x) {
                                $target = ['x' => (float) ($group['x'] + $grid['x'] + $x), 'y' => (float) ($grid['y'] + $y), 'z' => (float) ($group['z'] + $grid['z'] + $z), 'width' => $width, 'length' => $length];

                                try {
                                    $editor->move($tour, $route, $crate->delivery->mission()->id(), $index, $target);
                                } catch (PlacementException) {
                                    continue;
                                }

                                return [$target['x'], $target['y'], $target['z'], $width, $length];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}
