<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CargoBlock;
use App\Domain\CargoGrid;
use App\Domain\Ship;
use App\Domain\ShipCatalog;
use PHPUnit\Framework\TestCase;

final class ShipCatalogTest extends TestCase
{
    public function testCatalogEntriesAreValid(): void
    {
        $ships = ShipCatalog::all();
        $raw = json_decode((string) file_get_contents(__DIR__.'/../../src/Domain/Data/ships.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(count($raw), $ships);
        self::assertGreaterThanOrEqual(100, count($ships));
        foreach ($raw as $row) {
            $ship = $ships[$row['slug']];
            self::assertSame($row['capacity'], $ship->capacity(), $row['slug']);
            self::assertSame($row['slug'], $ship->slug);
            self::assertSame($row['manufacturer'].' '.$row['name'], $ship->label());
            $blocks = $ship->grid->blocks;
            foreach ($blocks as $i => $a) {
                foreach ($blocks as $j => $b) {
                    if ($i < $j) {
                        self::assertFalse($this->overlap($a, $b), sprintf('%s blocks %d/%d overlap', $row['slug'], $i, $j));
                    }
                }
            }
        }
    }

    public function testSortedByLabelAndCached(): void
    {
        $labels = array_values(array_map(static fn (Ship $s): string => $s->label(), ShipCatalog::all()));
        $sorted = $labels;
        usort($sorted, 'strcasecmp');

        self::assertSame($sorted, $labels);
        self::assertSame(ShipCatalog::all(), ShipCatalog::all());
    }

    public function testKnownShips(): void
    {
        self::assertArrayHasKey(ShipCatalog::DEFAULT, ShipCatalog::all());
        self::assertSame(576, ShipCatalog::get('drake-caterpillar')->capacity());
        self::assertSame(696, ShipCatalog::get('crusader-c2-hercules')->capacity());
        self::assertSame(120, ShipCatalog::get('misc-freelancer-max')->capacity());
        self::assertSame(96, ShipCatalog::get('rsi-constellation-mk-iv-andromeda')->capacity());
        self::assertSame(46, ShipCatalog::get('drake-cutlass-black')->capacity());
        self::assertSame(4608, ShipCatalog::get('misc-hull-c')->capacity());
        self::assertSame(2216, ShipCatalog::get('drake-ironclad')->capacity());
        self::assertSame('Drake Caterpillar', ShipCatalog::get('drake-caterpillar')->label());
    }

    public function testIdrisHasMaxSizeBlocks(): void
    {
        $idris = ShipCatalog::get('aegis-idris-m');

        self::assertCount(21, $idris->grid->blocks);
        self::assertSame(16, $idris->grid->blocks[16]->maxSize);
        self::assertNull($idris->grid->blocks[0]->maxSize);
        self::assertSame(16, $idris->grid->maxCrateSize());
    }

    public function testUnknownShip(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ShipCatalog::get('nope');
    }

    public function testGridLookups(): void
    {
        $grid = new CargoGrid([new CargoBlock(0, 0, 0, 2, 2, 1), new CargoBlock(4, 0, 0, 2, 2, 1, 2)]);

        self::assertSame(6, $grid->width);
        self::assertSame(8, $grid->capacity());
        self::assertTrue($grid->contains(1, 1, 0));
        self::assertFalse($grid->contains(3, 0, 0), 'gap between blocks');
        self::assertSame(2, $grid->blockAt(5, 1, 0)?->maxSize);
        self::assertNull($grid->blockAt(0, 0, 1));
    }

    private function overlap(CargoBlock $a, CargoBlock $b): bool
    {
        return $a->x < $b->x + $b->width && $b->x < $a->x + $a->width
            && $a->y < $b->y + $b->length && $b->y < $a->y + $a->length
            && $a->z < $b->z + $b->height && $b->z < $a->z + $a->height;
    }
}
