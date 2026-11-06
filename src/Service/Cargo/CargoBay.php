<?php

declare(strict_types=1);

namespace App\Service\Cargo;

/**
 * Une grille de la soute et ses cellules occupées (1 cellule = 1 SCU).
 */
final class CargoBay
{
    /** @var array<int, true> */
    private array $occupied = [];

    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $z,
        public readonly int $width,
        public readonly int $height,
        public readonly int $length,
        private readonly ?int $minSize,
        private readonly ?int $maxSize,
    ) {
    }

    /**
     * @param array<string, mixed> $group
     * @param array<string, mixed> $grid
     */
    public static function fromGrid(array $group, array $grid): self
    {
        return new self(
            $group['x'] + $grid['x'],
            (float) $grid['y'],
            $group['z'] + $grid['z'],
            (int) $grid['width'],
            (int) $grid['height'],
            (int) $grid['length'],
            $grid['minSize'] ?? $group['minSize'] ?? null,
            $grid['maxSize'] ?? null,
        );
    }

    public function largestCrate(): int
    {
        return $this->maxSize ?? 32;
    }

    /**
     * @return list<Spot>
     */
    public function spotsFor(int $size): array
    {
        if (!$this->accepts($size)) {
            return [];
        }

        $dimensions = CrateSize::dimensions($size);
        $orientations = array_unique([[$dimensions['width'], $dimensions['length']], [$dimensions['length'], $dimensions['width']]], SORT_REGULAR);

        return array_merge(...array_map(fn (array $footprint): array => $this->freeSpots($footprint[0], $dimensions['height'], $footprint[1]), $orientations));
    }

    public function availableSpot(float $x, float $y, float $z, int $width, int $height, int $length): ?Spot
    {
        $spot = new Spot($this, (int) round($x - $this->x), (int) round($y - $this->y), (int) round($z - $this->z), $width, $height, $length);
        $inside = $spot->offsetX >= 0 && $spot->offsetY >= 0 && $spot->offsetZ >= 0
            && $spot->offsetX + $width <= $this->width && $spot->offsetY + $height <= $this->height && $spot->offsetZ + $length <= $this->length;

        return $inside && $this->isFree($spot) ? $spot : null;
    }

    public function occupy(Spot $spot): void
    {
        foreach ($this->cellsOf($spot) as $cell) {
            $this->occupied[$cell] = true;
        }
    }

    public function free(Spot $spot): void
    {
        foreach ($this->cellsOf($spot) as $cell) {
            unset($this->occupied[$cell]);
        }
    }

    private function accepts(int $size): bool
    {
        return $size <= ($this->maxSize ?? 32) && $size >= ($this->minSize ?? 1);
    }

    /**
     * @return list<Spot>
     */
    private function freeSpots(int $width, int $height, int $length): array
    {
        $candidates = [];

        foreach ($this->positions($width, $height, $length) as [$x, $y, $z]) {
            $candidates[] = new Spot($this, $x, $y, $z, $width, $height, $length);
        }

        return array_values(array_filter($candidates, fn (Spot $spot): bool => $this->isFree($spot) && $this->isSupported($spot)));
    }

    /**
     * @return \Generator<array{int, int, int}>
     */
    private function positions(int $width, int $height, int $length): \Generator
    {
        if ($width > $this->width || $height > $this->height || $length > $this->length) {
            return;
        }

        $columns = range(0, $this->width - $width);
        $layers = range(0, $this->height - $height);
        $rows = range(0, $this->length - $length);

        foreach ($layers as $y) {
            yield from array_merge(...array_map(static fn (int $z): array => array_map(static fn (int $x): array => [$x, $y, $z], $columns), $rows));
        }
    }

    private function isFree(Spot $spot): bool
    {
        return [] === array_intersect_key(array_flip($this->cellsOf($spot)), $this->occupied);
    }

    private function isSupported(Spot $spot): bool
    {
        if (0 === $spot->offsetY) {
            return true;
        }

        $below = new Spot($this, $spot->offsetX, $spot->offsetY - 1, $spot->offsetZ, $spot->width, 1, $spot->length);

        return \count(array_intersect_key(array_flip($this->cellsOf($below)), $this->occupied)) === $spot->width * $spot->length;
    }

    /**
     * @return list<int>
     */
    private function cellsOf(Spot $spot): array
    {
        $cells = [];

        foreach (range($spot->offsetY, $spot->offsetY + $spot->height - 1) as $y) {
            $cells = [...$cells, ...$this->layerCells($spot, $y)];
        }

        return $cells;
    }

    /**
     * @return list<int>
     */
    private function layerCells(Spot $spot, int $y): array
    {
        $columns = range($spot->offsetX, $spot->offsetX + $spot->width - 1);
        $rows = range($spot->offsetZ, $spot->offsetZ + $spot->length - 1);

        return array_merge(...array_map(fn (int $z): array => array_map(fn (int $x): int => $x + $this->width * ($z + $this->length * $y), $columns), $rows));
    }
}
