<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Route;

use App\Entity\Tour;
use App\Service\Route\Route;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\ManualOrderInvariants;
use App\Tests\Philosophy\Support\RandomTours;
use App\Tests\Philosophy\Support\RouteInvariants;
use App\Tests\Philosophy\Support\TourBlueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ManualOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{TourBlueprint, RouteMode, int}>
     */
    public static function generatedTours(): iterable
    {
        foreach (range(2001, 2040) as $seed) {
            $blueprint = (new RandomTours($seed))->routeTour(6);

            foreach (RouteMode::cases() as $mode) {
                yield $blueprint->name().' · '.$mode->label() => [$blueprint, $mode, $seed];
            }
        }
    }

    #[DataProvider('generatedTours')]
    public function testShuffledManualOrderIsFollowedThenCompleted(TourBlueprint $blueprint, RouteMode $mode, int $seed): void
    {
        $manualOrder = $this->shuffledOrder($blueprint, $mode, $seed);
        $manual = $blueprint->withStopOrder($manualOrder, 'ordre manuel mélangé');
        $tour = $manual->tour();
        $route = (new RoutePlanner())->plan($tour, $mode);
        $case = $manual->name().' · '.$mode->label();

        (new RouteInvariants($tour, $route, $case))->assertCommonPhilosophy();
        (new ManualOrderInvariants($tour, $route, $mode, $case))->assertManualOrderFollowed($manualOrder);
        self::assertTrue($route->customOrder, 'le parcours doit se signaler comme posé à la main · '.$case);
        $this->assertMissionFocusRule($tour, $route, $mode, $case);
    }

    private function assertMissionFocusRule(Tour $tour, Route $route, RouteMode $mode, string $case): void
    {
        if (RouteMode::MissionFocus !== $mode) {
            return;
        }

        (new RouteInvariants($tour, $route, $case))->assertAtMostOneMissionInProgress();
    }

    #[DataProvider('generatedTours')]
    public function testPostingTheComputedOrderBackChangesNothing(TourBlueprint $blueprint, RouteMode $mode, int $seed): void
    {
        $computed = (new RoutePlanner())->plan($blueprint->tour(), $mode);
        $manual = $blueprint->withStopOrder($computed->stopLocations(), 'ordre calculé reposé à la main');
        $replayed = (new RoutePlanner())->plan($manual->tour(), $mode);

        self::assertSame($computed->stopLocations(), $replayed->stopLocations(), sprintf('ordre manuel : reposer l\'ordre calculé tel quel modifie le parcours · %s · %s', $manual->name(), $mode->label()));
    }

    /**
     * @return list<string>
     */
    private function shuffledOrder(TourBlueprint $blueprint, RouteMode $mode, int $seed): array
    {
        $random = new RandomTours($seed);
        $locations = array_values(array_unique((new RoutePlanner())->plan($blueprint->tour(), $mode)->stopLocations()));
        $shuffled = $random->shuffled($locations);

        return $random->chance(30) ? \array_slice($shuffled, 0, max(1, \count($shuffled) - 1)) : $shuffled;
    }
}
