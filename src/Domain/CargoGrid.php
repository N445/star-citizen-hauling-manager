<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class CargoGrid
{
    /** Bounding box extents (blocks use absolute coordinates starting at 0). */
    public int $width;
    public int $length;
    public int $height;

    /**
     * @param list<CargoBlock> $blocks
     */
    public function __construct(
        public array $blocks,
        public DoorSide $door = DoorSide::Rear,
    ) {
        if ([] === $blocks) {
            throw new \InvalidArgumentException('A cargo grid needs at least one block.');
        }
        $this->width = max(array_map(static fn (CargoBlock $b): int => $b->x + $b->width, $blocks));
        $this->length = max(array_map(static fn (CargoBlock $b): int => $b->y + $b->length, $blocks));
        $this->height = max(array_map(static fn (CargoBlock $b): int => $b->z + $b->height, $blocks));
    }

    /** Single full block grid. */
    public static function box(int $width, int $length, int $height, DoorSide $door = DoorSide::Rear): self
    {
        return new self([new CargoBlock(0, 0, 0, $width, $length, $height)], $door);
    }

    public function withDoor(DoorSide $door): self
    {
        return new self($this->blocks, $door);
    }

    public function capacity(): int
    {
        return array_sum(array_map(static fn (CargoBlock $b): int => $b->volume(), $this->blocks));
    }

    public function contains(int $x, int $y, int $z): bool
    {
        return null !== $this->blockAt($x, $y, $z);
    }

    public function blockAt(int $x, int $y, int $z): ?CargoBlock
    {
        foreach ($this->blocks as $block) {
            if ($block->contains($x, $y, $z)) {
                return $block;
            }
        }

        return null;
    }

    /** Whether some block could hold the crate when empty. */
    public function accepts(Crate $crate): bool
    {
        foreach ($this->blocks as $block) {
            if ($block->accepts($crate)) {
                return true;
            }
        }

        return false;
    }

    /** Largest standard crate size (SCU) that some block can hold. */
    public function maxCrateSize(): int
    {
        $max = 0;
        foreach (Crate::SIZES as $size) {
            if ($this->accepts(new Crate('probe', 'probe', 'probe', $size))) {
                $max = $size;
            }
        }

        return $max;
    }

    /** Number of cells between a cell and the loading door (0 = at the door). */
    public function doorDistance(int $x, int $y): int
    {
        return match ($this->door) {
            DoorSide::Rear => $this->length - 1 - $y,
            DoorSide::Front => $y,
            DoorSide::Left => $x,
            DoorSide::Right => $this->width - 1 - $x,
        };
    }
}
