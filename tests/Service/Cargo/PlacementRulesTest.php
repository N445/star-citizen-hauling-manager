<?php

declare(strict_types=1);

namespace App\Tests\Service\Cargo;

use App\Service\Cargo\PlacementException;
use App\Service\Cargo\PlacementRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlacementRulesTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private static function holdGroups(?int $maxSize = null): array
    {
        return [['x' => 0, 'z' => 0, 'grids' => [['x' => 0, 'y' => 0, 'z' => 0, 'width' => 4, 'height' => 3, 'length' => 6] + (null === $maxSize ? [] : ['maxSize' => $maxSize])]]];
    }

    /**
     * @return array{size: int, x: float, y: float, z: float, width: int, height: int, length: int}
     */
    private static function crate(int $size, float $x, float $y, float $z, int $width, int $height, int $length): array
    {
        return ['size' => $size, 'x' => $x, 'y' => $y, 'z' => $z, 'width' => $width, 'height' => $height, 'length' => $length];
    }

    public function testCrateOnTheFloorIsAccepted(): void
    {
        PlacementRules::assertLayout(self::holdGroups(), [], [self::crate(4, 0, 0, 0, 2, 1, 2)]);

        $this->addToAssertionCount(1);
    }

    public function testCrateCanBeTurnedAQuarter(): void
    {
        PlacementRules::assertLayout(self::holdGroups(), [], [self::crate(2, 0, 0, 0, 2, 1, 1)]);

        $this->addToAssertionCount(1);
    }

    public function testCrateCanBeStackedOnCratesCoveringItsWholeFootprint(): void
    {
        $below = self::crate(4, 0, 0, 0, 2, 1, 2);

        PlacementRules::assertLayout(self::holdGroups(), [$below], [self::crate(4, 0, 1, 0, 2, 1, 2)]);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, list<array<string, mixed>>, array<string, mixed>}>
     */
    public static function refusals(): iterable
    {
        yield 'hors de la grille' => ['cargo.edit.error.outside', [], self::crate(4, 3, 0, 0, 2, 1, 2)];
        yield 'sous le sol de la grille' => ['cargo.edit.error.outside', [], self::crate(4, 0, -1, 0, 2, 1, 2)];
        yield 'au-dessus de la hauteur' => ['cargo.edit.error.outside', [], self::crate(8, 0, 2, 0, 2, 2, 2)];
        yield 'chevauchement' => ['cargo.edit.error.overlap', [self::crate(4, 1, 0, 1, 2, 1, 2)], self::crate(4, 0, 0, 0, 2, 1, 2)];
        yield 'en l air' => ['cargo.edit.error.unsupported', [], self::crate(4, 0, 1, 0, 2, 1, 2)];
        yield 'appui partiel' => ['cargo.edit.error.unsupported', [self::crate(1, 0, 0, 0, 1, 1, 1)], self::crate(4, 0, 1, 0, 2, 1, 2)];
        yield 'dimensions inconnues' => ['cargo.edit.error.shape', [], self::crate(4, 0, 0, 0, 1, 1, 4)];
        yield 'hauteur de la mauvaise taille' => ['cargo.edit.error.shape', [], self::crate(4, 0, 0, 0, 2, 2, 2)];
    }

    /**
     * @param list<array<string, mixed>> $fixed
     * @param array<string, mixed>       $moved
     */
    #[DataProvider('refusals')]
    public function testRefusals(string $expected, array $fixed, array $moved): void
    {
        $this->expectException(PlacementException::class);
        $this->expectExceptionMessage($expected);

        PlacementRules::assertLayout(self::holdGroups(), $fixed, [$moved]);
    }

    public function testGridMaximumSizeIsRespected(): void
    {
        $this->expectExceptionObject(new PlacementException('cargo.edit.error.size'));

        PlacementRules::assertLayout(self::holdGroups(8), [], [self::crate(16, 0, 0, 0, 2, 2, 4)]);
    }

    public function testGridMinimumSizeIsRespected(): void
    {
        $groups = [['x' => 0, 'z' => 0, 'minSize' => 8, 'grids' => [['x' => 0, 'y' => 0, 'z' => 0, 'width' => 4, 'height' => 2, 'length' => 4]]]];

        $this->expectExceptionObject(new PlacementException('cargo.edit.error.size'));

        PlacementRules::assertLayout($groups, [], [self::crate(4, 0, 0, 0, 2, 1, 2)]);
    }

    public function testMovingACrateMayNotRemoveTheSupportOfAnother(): void
    {
        $above = self::crate(4, 0, 1, 0, 2, 1, 2);

        $this->expectExceptionObject(new PlacementException('cargo.edit.error.supports'));

        PlacementRules::assertLayout(self::holdGroups(), [$above], [self::crate(4, 2, 0, 4, 2, 1, 2)]);
    }

    public function testCratesMovedTogetherMayStackOnEachOther(): void
    {
        PlacementRules::assertLayout(self::holdGroups(), [], [self::crate(4, 0, 1, 0, 2, 1, 2), self::crate(4, 0, 0, 0, 2, 1, 2)]);

        $this->addToAssertionCount(1);
    }
}
