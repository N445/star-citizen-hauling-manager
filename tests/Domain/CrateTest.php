<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CargoLot;
use App\Domain\Crate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CrateTest extends TestCase
{
    /**
     * @param list<int> $expected
     */
    #[DataProvider('splits')]
    public function testGreedySplit(int $scu, array $expected): void
    {
        $lot = new CargoLot('l1', 'm1', 'Gold', $scu, DomainFactory::place('orison'), DomainFactory::place('area18'));

        $crates = Crate::split($lot);

        self::assertSame($expected, array_map(static fn (Crate $c): int => $c->sizeScu, $crates));
        foreach ($crates as $crate) {
            self::assertSame('m1', $crate->missionId);
            self::assertSame('l1', $crate->lotId);
        }
    }

    /** @return iterable<string, array{int, list<int>}> */
    public static function splits(): iterable
    {
        yield 'single' => [1, [1]];
        yield 'exact' => [24, [24]];
        yield '40' => [40, [32, 8]];
        yield '56' => [56, [32, 24]];
        yield '30' => [30, [24, 4, 2]];
        yield '71' => [71, [32, 32, 4, 2, 1]];
        yield 'empty' => [0, []];
    }

    public function testRejectsInvalidSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Crate('c', 'l', 'm', 3);
    }

    public function testOrientationsKeepVolume(): void
    {
        foreach (Crate::SIZES as $size) {
            $crate = new Crate('c', 'l', 'm', $size);
            foreach ($crate->orientations() as [$w, $l, $h]) {
                self::assertSame($size, $w * $l * $h);
            }
        }
    }

    public function testLotWithCratesIsLazyAndIdempotent(): void
    {
        $lot = new CargoLot('l1', 'm1', 'Gold', 10, DomainFactory::place('orison'), DomainFactory::place('area18'));

        $cut = $lot->withCrates();

        self::assertSame([8, 2], array_map(static fn (Crate $c): int => $c->sizeScu, $cut->crates));
        self::assertSame($cut, $cut->withCrates());
    }
}
