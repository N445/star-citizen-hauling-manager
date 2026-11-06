<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Scenario;

use App\Service\Route\Route;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\HandWrittenTours;
use App\Tests\Philosophy\Support\ManualOrderInvariants;
use App\Tests\Philosophy\Support\MissionBlueprint;
use App\Tests\Philosophy\Support\RouteInvariants;
use App\Tests\Philosophy\Support\TourBlueprint;
use PHPUnit\Framework\TestCase;

final class RouteReferenceScenariosTest extends TestCase
{
    public function testUserReferenceInFewestStopsChainsTheStationsWithoutDetour(): void
    {
        $route = $this->plan(HandWrittenTours::userReference(), RouteMode::FewestStops);

        self::assertSame(
            [HandWrittenTours::EVERUS, HandWrittenTours::HUR_L2, HandWrittenTours::HUR_L3, HandWrittenTours::HUR_L1, HandWrittenTours::EVERUS],
            array_map(static fn ($stop): string => $stop->location, $route->stops),
            'cas de référence : « moins d\'arrêts » doit faire Everus → HUR-L2 → HUR-L3 → HUR-L1 → Everus',
        );
        self::assertSame(4, $route->moveCount(), 'cas de référence : 4 déplacements attendus en « moins d\'arrêts »');
    }

    public function testUserReferenceInMissionFocusNeedsFiveMoves(): void
    {
        $route = $this->plan(HandWrittenTours::userReference(), RouteMode::MissionFocus);

        self::assertSame(5, $route->moveCount(), 'cas de référence : 5 déplacements attendus en « mission par mission »');
    }

    public function testLoadingEverythingFirstThenDeliveringNeedsOneStopPerPlace(): void
    {
        $route = $this->plan(HandWrittenTours::loadEverythingThenDeliver(4, 20), RouteMode::FewestStops);

        self::assertCount(5, $route->stops, 'tout charger puis tout livrer : un ramassage puis une livraison par destination');
        self::assertCount(4, $route->stops[0]->pickups, 'tout charger puis tout livrer : les 4 missions sont ramassées au premier arrêt');
    }

    public function testTightCapacityMakesBothModesEqual(): void
    {
        $blueprint = HandWrittenTours::tightCapacity();
        $fewest = $this->plan($blueprint, RouteMode::FewestStops);
        $focus = $this->plan($blueprint, RouteMode::MissionFocus);

        self::assertCount(\count($focus->stops), $fewest->stops, 'capacité très serrée : une seule mission tient à bord, les deux modes doivent faire autant d\'arrêts');
        $tour = $blueprint->tour();
        (new RouteInvariants($tour, (new RoutePlanner())->plan($tour, RouteMode::FewestStops), $blueprint->name()))->assertAtMostOneMissionInProgress();
    }

    public function testMultiplePickupsAreAllVisitedBeforeTheDelivery(): void
    {
        $route = $this->plan(HandWrittenTours::multiplePickups(), RouteMode::FewestStops);
        $locations = array_map(static fn ($stop): string => $stop->location, $route->stops);
        $deliveryStep = array_search('Area 18', $locations, true);

        foreach (['Lorville', 'Orison', 'New Babbage'] as $pickup) {
            self::assertLessThan($deliveryStep, array_search($pickup, $locations, true), sprintf('ramassages multiples : %s doit être visité avant la livraison à Area 18', $pickup));
        }
    }

    public function testOversizedMissionIsRefused(): void
    {
        $blueprint = new TourBlueprint('mission de 120 SCU pour 100 SCU', 100, null, [MissionBlueprint::direct('Lorville', 'Area 18', 120)]);

        $this->expectException(\DomainException::class);

        $this->plan($blueprint, RouteMode::FewestStops);
    }

    public function testManualOrderOnTheReferenceStartsWhereTheUserSaid(): void
    {
        $manualOrder = [HandWrittenTours::HUR_L1, HandWrittenTours::HUR_L2, HandWrittenTours::HUR_L3, HandWrittenTours::EVERUS];
        $blueprint = HandWrittenTours::userReference()->withStopOrder($manualOrder, 'ordre manuel HUR-L1 d\'abord');
        $tour = $blueprint->tour();
        $route = (new RoutePlanner())->plan($tour, RouteMode::FewestStops);

        self::assertSame(HandWrittenTours::HUR_L1, $route->stops[1]->location, 'ordre manuel : le premier arrêt posé à la main doit suivre le départ');
        (new RouteInvariants($tour, $route, $blueprint->name()))->assertCommonPhilosophy();
        (new ManualOrderInvariants($tour, $route, RouteMode::FewestStops, $blueprint->name()))->assertManualOrderFollowed($manualOrder);
    }

    public function testFewestStopsDoesNotFallIntoTheGreedyTrap(): void
    {
        $blueprint = HandWrittenTours::greedyTrap();
        $fewest = $this->plan($blueprint, RouteMode::FewestStops);
        $focus = $this->plan($blueprint, RouteMode::MissionFocus);

        self::assertLessThanOrEqual(\count($focus->stops), \count($fewest->stops), sprintf('« moins d\'arrêts » (%s) fait plus d\'arrêts que « mission par mission » (%s)', implode(' → ', $fewest->stopLocations()), implode(' → ', $focus->stopLocations())));
    }

    public function testFreezingTheMissionFocusOrderKeepsTheSameRoute(): void
    {
        $blueprint = HandWrittenTours::greedyTrap();
        $computed = $this->plan($blueprint, RouteMode::MissionFocus);
        $frozen = $this->plan($blueprint->withStopOrder($computed->stopLocations(), 'ordre figé'), RouteMode::MissionFocus);

        self::assertSame($computed->stopLocations(), $frozen->stopLocations(), 'ordre manuel : figer l\'ordre affiché (première case cochée) change le parcours en « mission par mission »');
    }

    private function plan(TourBlueprint $blueprint, RouteMode $mode): Route
    {
        $tour = $blueprint->tour();
        $route = (new RoutePlanner())->plan($tour, $mode);
        (new RouteInvariants($tour, $route, $blueprint->name().' · '.$mode->label()))->assertCommonPhilosophy();

        return $route;
    }
}
