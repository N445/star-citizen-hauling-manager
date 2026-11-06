<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\DoorSide;
use App\Entity\Ship;
use App\Service\Cargo\Spot;

final readonly class CargoRoom
{
    /**
     * @param list<array{x: float, y: float, z: float, width: int, height: int, length: int, minSize: int, maxSize: int}> $grids
     */
    private function __construct(private array $grids)
    {
    }

    public static function of(Ship $ship): self
    {
        $grids = [];

        foreach ($ship->cargoGroups() as $group) {
            foreach ($group['grids'] as $grid) {
                $grids[] = [
                    'x' => (float) ($group['x'] + $grid['x']),
                    'y' => (float) $grid['y'],
                    'z' => (float) ($group['z'] + $grid['z']),
                    'width' => $grid['width'],
                    'height' => $grid['height'],
                    'length' => $grid['length'],
                    'minSize' => $grid['minSize'] ?? $group['minSize'] ?? 1,
                    'maxSize' => $grid['maxSize'] ?? 32,
                ];
            }
        }

        return new self($grids);
    }

    /**
     * @return array{x: float, y: float, z: float, width: int, height: int, length: int, minSize: int, maxSize: int}|null
     */
    public function gridOf(Spot $spot): ?array
    {
        $matching = array_filter($this->grids, static fn (array $grid): bool => abs($grid['x'] - $spot->bay->x) < 0.001
            && abs($grid['y'] - $spot->bay->y) < 0.001
            && abs($grid['z'] - $spot->bay->z) < 0.001
            && $grid['width'] === $spot->bay->width
            && $grid['height'] === $spot->bay->height
            && $grid['length'] === $spot->bay->length);

        return [] === $matching ? null : reset($matching);
    }

    public function floorCapacityForSingleUnits(): int
    {
        return array_sum(array_map(static fn (array $grid): int => $grid['width'] * $grid['length'] * min(2, $grid['height']), $this->gridsAcceptingSingleUnits()));
    }

    public function volumeForSingleUnits(): int
    {
        return array_sum(array_map(static fn (array $grid): int => $grid['width'] * $grid['length'] * $grid['height'], $this->gridsAcceptingSingleUnits()));
    }

    public function doorPlane(DoorSide $door): float
    {
        $axis = $door->axis();
        $size = ['x' => 'width', 'y' => 'height', 'z' => 'length'][$axis];
        $edges = array_map(static fn (array $grid): float => $grid[$axis] + ($door->isAtMaximum() ? $grid[$size] : 0), $this->grids);

        return $door->isAtMaximum() ? max($edges) : min($edges);
    }

    /**
     * @return list<array{x: float, y: float, z: float, width: int, height: int, length: int, minSize: int, maxSize: int}>
     */
    private function gridsAcceptingSingleUnits(): array
    {
        return array_values(array_filter($this->grids, static fn (array $grid): bool => 1 === $grid['minSize']));
    }
}
