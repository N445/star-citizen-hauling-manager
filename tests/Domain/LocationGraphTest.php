<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Location;
use App\Domain\LocationGraph;
use App\Domain\LocationType;
use App\Domain\StantonLocations;
use App\Domain\TravelTier;
use PHPUnit\Framework\TestCase;

final class LocationGraphTest extends TestCase
{
    public function testTiers(): void
    {
        $orison = DomainFactory::place('orison');
        $seraphim = DomainFactory::place('seraphim-station');
        $daymar = DomainFactory::place('kudre-ore');
        $daymar2 = DomainFactory::place('bountiful-harvest');
        $yela = DomainFactory::place('benson-mining');
        $l1 = DomainFactory::place('cru-l1');
        $area18 = DomainFactory::place('area18');
        $pyro = new Location('pyro-gateway', 'Pyro Gateway', 'Pyro', 'Pyro', LocationType::Orbital);

        self::assertSame(TravelTier::Local, LocationGraph::tier($orison, $orison));
        self::assertSame(TravelTier::Local, LocationGraph::tier($orison, $seraphim));
        self::assertSame(TravelTier::Local, LocationGraph::tier($daymar, $daymar2));
        self::assertSame(TravelTier::Planetary, LocationGraph::tier($orison, $daymar));
        self::assertSame(TravelTier::Planetary, LocationGraph::tier($seraphim, $l1));
        self::assertSame(TravelTier::Planetary, LocationGraph::tier($daymar, $yela));
        self::assertSame(TravelTier::Stellar, LocationGraph::tier($orison, $area18));
        self::assertSame(TravelTier::Stellar, LocationGraph::tier($daymar, $area18));
        self::assertSame(TravelTier::Interstellar, LocationGraph::tier($orison, $pyro));
        self::assertSame(LocationGraph::tier($area18, $daymar), LocationGraph::tier($daymar, $area18));
        self::assertSame([0, 1, 3, 10], array_map(static fn (TravelTier $t): int => $t->cost(), TravelTier::cases()));
    }

    public function testStantonLocationsAreConsistent(): void
    {
        $all = StantonLocations::all();

        self::assertGreaterThan(15, count($all));
        foreach ($all as $id => $location) {
            self::assertSame($id, $location->id);
            self::assertSame('Stanton', $location->system);
        }
        self::assertSame('Orison', StantonLocations::get('orison')->name);

        $this->expectException(\InvalidArgumentException::class);
        StantonLocations::get('nowhere');
    }
}
