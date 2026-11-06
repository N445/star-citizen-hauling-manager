<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Per-block, per-cell occupation over stop intervals. A cell is in conflict when another crate
 * occupies it on a stop that overlaps [pickup, drop] (both inclusive).
 */
final class CargoOccupancy
{
    /** @var array<int, array<int, list<array{int, int, string}>>> block => local cell => [pickup, drop, crateId] */
    private array $cells;

    public function __construct(private readonly CargoGrid $grid)
    {
        $this->cells = array_fill_keys(array_keys($grid->blocks), []);
    }

    public function add(PlacedCrate $placed): void
    {
        $block = $this->grid->blocks[$placed->blockIndex];
        foreach (self::cellsOf($block, $placed->x, $placed->y, $placed->z, $placed->width, $placed->length, $placed->height) as $cell) {
            $this->cells[$placed->blockIndex][$cell][] = [$placed->pickupStop, $placed->dropStop, $placed->crate->id];
        }
    }

    /** Id of a crate in conflict with the given box, or null when free. */
    public function conflict(int $blockIndex, int $x, int $y, int $z, int $w, int $l, int $h, int $pickup, int $drop): ?string
    {
        $cells = $this->cells[$blockIndex];
        if ([] === $cells) {
            return null;
        }
        foreach (self::cellsOf($this->grid->blocks[$blockIndex], $x, $y, $z, $w, $l, $h) as $cell) {
            foreach ($cells[$cell] ?? [] as [$otherPickup, $otherDrop, $otherId]) {
                if ($pickup <= $otherDrop && $otherPickup <= $drop) {
                    return $otherId;
                }
            }
        }

        return null;
    }

    public function isFree(int $blockIndex, int $x, int $y, int $z, int $w, int $l, int $h, int $pickup, int $drop): bool
    {
        return null === $this->conflict($blockIndex, $x, $y, $z, $w, $l, $h, $pickup, $drop);
    }

    /** Index of the single block containing the whole box, or null. */
    public function blockIndex(int $x, int $y, int $z, int $w, int $l, int $h): ?int
    {
        foreach ($this->grid->blocks as $index => $block) {
            if ($block->contains($x, $y, $z) && $block->contains($x + $w - 1, $y + $l - 1, $z + $h - 1)) {
                return $index;
            }
        }

        return null;
    }

    /** French refusal message for a crate dropped at a position, or null if allowed. */
    public function reason(Crate $crate, int $pickup, int $drop, int $orientation, int $x, int $y, int $z): ?string
    {
        $orientations = $crate->orientations();
        if (!isset($orientations[$orientation])) {
            return 'Orientation invalide.';
        }
        [$w, $l, $h] = $orientations[$orientation];

        $index = $this->blockIndex($x, $y, $z, $w, $l, $h);
        if (null === $index) {
            $far = $this->grid->blockAt($x + $w - 1, $y + $l - 1, $z + $h - 1);
            $near = $this->grid->blockAt($x, $y, $z);
            if (null !== $far && null !== $near) {
                return 'La caisse est à cheval sur deux blocs de soute.';
            }

            return 'La caisse sort de la soute.';
        }

        $max = $this->grid->blocks[$index]->maxSize;
        if (null !== $max && $crate->sizeScu > $max) {
            return sprintf('Une caisse de %d SCU dépasse la taille maximale de ce bloc (%d SCU).', $crate->sizeScu, $max);
        }

        $other = $this->conflict($index, $x, $y, $z, $w, $l, $h, $pickup, $drop);

        return null === $other ? null : sprintf('Chevauche la caisse %s.', $other);
    }

    /** @return \Generator<int> block-local cell indexes */
    public static function cellsOf(CargoBlock $block, int $x, int $y, int $z, int $w, int $l, int $h): \Generator
    {
        for ($k = $z - $block->z; $k < $z - $block->z + $h; ++$k) {
            for ($j = $y - $block->y; $j < $y - $block->y + $l; ++$j) {
                for ($i = $x - $block->x; $i < $x - $block->x + $w; ++$i) {
                    yield $i + $block->width * ($j + $block->length * $k);
                }
            }
        }
    }
}
