<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Route;

use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\RandomTours;
use App\Tests\Philosophy\Support\RouteInvariants;
use App\Tests\Philosophy\Support\TourBlueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoutePhilosophyTest extends TestCase
{
    private const int FIRST_SEED = 1;

    private const int CASE_COUNT = 150;

    private const int MAXIMUM_MISSIONS = 8;

    /**
     * @return iterable<string, array{TourBlueprint}>
     */
    public static function generatedTours(): iterable
    {
        foreach (range(self::FIRST_SEED, self::FIRST_SEED + self::CASE_COUNT - 1) as $seed) {
            $blueprint = (new RandomTours($seed))->routeTour(self::MAXIMUM_MISSIONS);
            yield $blueprint->name() => [$blueprint];
        }
    }

    #[DataProvider('generatedTours')]
    public function testFewestStopsRespectsThePhilosophy(TourBlueprint $blueprint): void
    {
        $this->invariants($blueprint, RouteMode::FewestStops)->assertCommonPhilosophy();
    }

    #[DataProvider('generatedTours')]
    public function testMissionFocusFinishesEachMissionBeforeTheNext(TourBlueprint $blueprint): void
    {
        $invariants = $this->invariants($blueprint, RouteMode::MissionFocus);

        $invariants->assertCommonPhilosophy();
        $invariants->assertAtMostOneMissionInProgress();
    }

    #[DataProvider('generatedTours')]
    public function testFewestStopsNeverStopsMoreThanMissionFocus(TourBlueprint $blueprint): void
    {
        $fewest = $this->invariants($blueprint, RouteMode::FewestStops);
        $focus = $this->invariants($blueprint, RouteMode::MissionFocus);

        self::assertLessThanOrEqual($focus->stopCount(), $fewest->stopCount(), sprintf(
            "« moins d'arrêts » fait plus d'arrêts que « mission par mission »\nCas : %s\nMoins d'arrêts :\n  %s\nMission par mission :\n  %s",
            $blueprint->name(),
            implode("\n  ", $fewest->signature()),
            implode("\n  ", $focus->signature()),
        ));
    }

    #[DataProvider('generatedTours')]
    public function testSameInputGivesSameRoute(TourBlueprint $blueprint): void
    {
        foreach (RouteMode::cases() as $mode) {
            self::assertSame(
                $this->invariants($blueprint, $mode)->signature(),
                $this->invariants($blueprint, $mode)->signature(),
                sprintf('déterminisme : deux calculs du même cas donnent deux parcours différents (%s) · %s', $mode->label(), $blueprint->name()),
            );
        }
    }

    private function invariants(TourBlueprint $blueprint, RouteMode $mode): RouteInvariants
    {
        $tour = $blueprint->tour();

        return new RouteInvariants($tour, (new RoutePlanner())->plan($tour, $mode), $blueprint->name().' · '.$mode->label());
    }
}
