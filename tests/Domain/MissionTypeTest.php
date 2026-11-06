<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CargoLot;
use App\Domain\CargoPlacer;
use App\Domain\Crate;
use App\Domain\Mission;
use App\Domain\MissionType;
use App\Domain\RouteOptimizer;
use App\Domain\ShipCatalog;
use PHPUnit\Framework\TestCase;

final class MissionTypeTest extends TestCase
{
    private function lot(string $id, string $mission, int $scu, string $from, ?string $to): CargoLot
    {
        return new CargoLot($id, $mission, 'Laranite', $scu, DomainFactory::place($from), null === $to ? null : DomainFactory::place($to));
    }

    public function testEnumValues(): void
    {
        self::assertSame(['direct', 'multipleDropOff', 'multiplePickUp', 'freeShopping'], array_map(static fn (MissionType $t): string => $t->value, MissionType::cases()));
    }

    public function testDirectMission(): void
    {
        $mission = new Mission('m', 'Direct', 100, [$this->lot('a', 'm', 8, 'orison', 'area18'), $this->lot('b', 'm', 4, 'orison', 'area18')]);
        $mission->validate();

        self::assertSame(MissionType::Direct, $mission->type);
        self::assertSame(32, $mission->maxContainerSize);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mission directe');
        (new Mission('m', 'Direct', 1, [$this->lot('a', 'm', 8, 'orison', 'area18'), $this->lot('b', 'm', 4, 'orison', 'lorville')]))->validate();
    }

    public function testMultipleDropOffHasNoRoundTrip(): void
    {
        $mission = new Mission('m', 'Multi drop', 100, [
            $this->lot('a', 'm', 8, 'orison', 'area18'),
            $this->lot('b', 'm', 8, 'orison', 'lorville'),
            $this->lot('c', 'm', 8, 'orison', 'area18'),
            $this->lot('d', 'm', 8, 'orison', 'lorville'),
        ], MissionType::MultipleDropOff);

        $route = (new RouteOptimizer())->optimize([$mission], DomainFactory::ship(), DomainFactory::place('orison'));

        $ids = array_map(static fn ($s): string => $s->location->id, $route->stops);
        self::assertCount(3, $ids);
        self::assertSame($ids, array_values(array_unique($ids)));
        self::assertSame('orison', $ids[0]);
    }

    public function testMultipleDropOffRejectsTwoPickups(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Mission('m', 'x', 1, [$this->lot('a', 'm', 8, 'orison', 'area18'), $this->lot('b', 'm', 8, 'lorville', 'area18')], MissionType::MultipleDropOff))->validate();
    }

    public function testMultiplePickUp(): void
    {
        $mission = new Mission('m', 'Multi pick', 100, [
            $this->lot('a', 'm', 8, 'orison', 'lorville'),
            $this->lot('b', 'm', 8, 'area18', 'lorville'),
        ], MissionType::MultiplePickUp);

        $route = (new RouteOptimizer())->optimize([$mission], DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame(['orison', 'area18', 'lorville'], array_map(static fn ($s): string => $s->location->id, $route->stops));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ramassages multiples');
        (new Mission('m', 'x', 1, [$this->lot('a', 'm', 8, 'orison', 'lorville'), $this->lot('b', 'm', 8, 'area18', 'area18')], MissionType::MultiplePickUp))->validate();
    }

    public function testFreeShoppingWithDestination(): void
    {
        $mission = new Mission('m', 'Shopping', 0, [
            $this->lot('a', 'm', 8, 'orison', 'area18'),
            $this->lot('b', 'm', 8, 'lorville', 'area18'),
        ], MissionType::FreeShopping);
        $mission->validate();

        $route = (new RouteOptimizer())->optimize([$mission], DomainFactory::ship(), DomainFactory::place('orison'));

        self::assertSame('area18', $route->stops[array_key_last($route->stops)]->location->id);
    }

    public function testFreeShoppingWithoutDestinationEndsAtLastPurchaseAndKeepsCrates(): void
    {
        $ship = DomainFactory::ship(4, 4, 1);
        $mission = new Mission('m', 'Shopping', 0, [
            $this->lot('a', 'm', 4, 'orison', null),
            $this->lot('b', 'm', 4, 'seraphim-station', null),
            $this->lot('c', 'm', 4, 'area18', null),
        ], MissionType::FreeShopping);
        $other = DomainFactory::mission('o', 'orison', 'seraphim-station', 4);

        $route = (new RouteOptimizer())->optimize([$mission, $other], $ship, DomainFactory::place('orison'));
        $placement = (new CargoPlacer())->place($route, $ship);

        $last = count($route->stops) - 1;
        self::assertSame('area18', $route->stops[$last]->location->id);
        self::assertSame([], $route->stops[$last]->drops);
        foreach (['a-1', 'b-1', 'c-1'] as $id) {
            $placed = $placement->forCrate($id);
            self::assertTrue($placed->kept, $id);
            self::assertSame($last, $placed->dropStop, $id);
            self::assertNotContains($id, array_map(static fn ($p): string => $p->crate->id, $placement->toDropAt($last)));
            self::assertContains($id, array_map(static fn ($p): string => $p->crate->id, $placement->gridAt($last)));
        }
        self::assertFalse($placement->forCrate('o-lot-1')->kept);
        $this->assertNoOverlapAtEveryStop($placement);
    }

    public function testMaxContainerSizeLimitsTheCut(): void
    {
        $mission = new Mission('m', 'Small crates', 1, [$this->lot('a', 'm', 70, 'orison', 'area18')], MissionType::Direct, 8);

        $route = (new RouteOptimizer())->optimize([$mission], ShipCatalog::get('misc-hull-c'), DomainFactory::place('orison'));

        $sizes = array_map(static fn (Crate $c): int => $c->sizeScu, $route->stops[0]->pickups);
        self::assertSame(70, array_sum($sizes));
        self::assertLessThanOrEqual(8, max($sizes));
        self::assertSame(8, $mission->maxContainerSize);
    }

    public function testMaxContainerSizeIsRoundedDownOrRefused(): void
    {
        self::assertSame(16, (new Mission('m', 'x', 1, [], MissionType::Direct, 20))->maxContainerSize);
        self::assertSame(4, (new Mission('m', 'x', 1, [], MissionType::Direct, 5))->maxContainerSize);
        self::assertSame(1, (new Mission('m', 'x', 1, [], MissionType::Direct, 1))->maxContainerSize);

        $this->expectException(\InvalidArgumentException::class);
        new Mission('m', 'x', 1, [], MissionType::Direct, 33);
    }

    public function testExplicitCrateAboveMissionLimitIsRefused(): void
    {
        $lot = new CargoLot('a', 'm', 'Gold', 16, DomainFactory::place('orison'), DomainFactory::place('area18'), [new Crate('a-1', 'a', 'm', 16)]);
        $mission = new Mission('m', 'Limited', 1, [$lot], MissionType::Direct, 8);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dépasse la taille maximale de la mission');
        (new RouteOptimizer())->optimize([$mission], DomainFactory::ship(), DomainFactory::place('orison'));
    }

    public function testLotWithSamePickupAndDropIsStillRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RouteOptimizer())->optimize(
            [new Mission('m', 'x', 1, [$this->lot('a', 'm', 4, 'orison', 'orison')])],
            DomainFactory::ship(),
            DomainFactory::place('orison'),
        );
    }

    private function assertNoOverlapAtEveryStop(\App\Domain\Placement $placement): void
    {
        foreach (array_keys($placement->route->stops) as $stop) {
            $cells = [];
            foreach ($placement->gridAt($stop) as $p) {
                for ($i = $p->x; $i < $p->x + $p->width; ++$i) {
                    for ($j = $p->y; $j < $p->y + $p->length; ++$j) {
                        for ($k = $p->z; $k < $p->z + $p->height; ++$k) {
                            self::assertArrayNotHasKey("$i/$j/$k", $cells);
                            $cells["$i/$j/$k"] = true;
                        }
                    }
                }
            }
        }
    }
}
