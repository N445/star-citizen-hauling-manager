<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\DoorSide;
use App\Service\Cargo\PlacedCrate;

final readonly class CrateBox
{
    private const float EPSILON = 0.001;

    public function __construct(private PlacedCrate $crate)
    {
    }

    public function overlaps(self $other): bool
    {
        return $this->overlapsAlong($other, 'x') && $this->overlapsAlong($other, 'y') && $this->overlapsAlong($other, 'z');
    }

    public function touches(self $other): bool
    {
        $axes = ['x', 'y', 'z'];
        $contacts = array_filter($axes, fn (string $axis): bool => $this->isFaceToFaceAlong($other, $axis));

        return [] !== $contacts;
    }

    public function restsOn(self $other): bool
    {
        return abs($this->start('y') - $other->end('y')) < self::EPSILON && $this->overlapsAlong($other, 'x') && $this->overlapsAlong($other, 'z');
    }

    public function isBetweenDoorAnd(self $other, DoorSide $door): bool
    {
        $axis = $door->axis();
        $crossAxes = array_values(array_diff(['x', 'y', 'z'], [$axis]));
        $towardsDoor = $door->isAtMaximum() ? $this->start($axis) >= $other->end($axis) - self::EPSILON : $this->end($axis) <= $other->start($axis) + self::EPSILON;

        return $towardsDoor && $this->overlapsAlong($other, $crossAxes[0]) && $this->overlapsAlong($other, $crossAxes[1]);
    }

    public function supportAreaFrom(self $other): float
    {
        if (!$this->restsOn($other)) {
            return 0.0;
        }

        return $this->overlapLength($other, 'x') * $this->overlapLength($other, 'z');
    }

    public function footprint(): float
    {
        return $this->size('x') * $this->size('z');
    }

    public function start(string $axis): float
    {
        return match ($axis) {
            'x' => $this->crate->spot->x(),
            'y' => $this->crate->spot->y(),
            'z' => $this->crate->spot->z(),
        };
    }

    public function end(string $axis): float
    {
        return $this->start($axis) + $this->size($axis);
    }

    public function center(string $axis): float
    {
        return $this->start($axis) + $this->size($axis) / 2;
    }

    public function describe(): string
    {
        return sprintf('caisse %s (%d SCU, livraison %d) en x=%s y=%s z=%s, %d×%d×%d', $this->crate->id, $this->crate->size, $this->crate->delivery->id(), $this->start('x'), $this->start('y'), $this->start('z'), $this->size('x'), $this->size('y'), $this->size('z'));
    }

    private function size(string $axis): int
    {
        return match ($axis) {
            'x' => $this->crate->spot->width,
            'y' => $this->crate->spot->height,
            'z' => $this->crate->spot->length,
        };
    }

    private function isFaceToFaceAlong(self $other, string $axis): bool
    {
        $adjacent = abs($this->end($axis) - $other->start($axis)) < self::EPSILON || abs($other->end($axis) - $this->start($axis)) < self::EPSILON;
        $crossAxes = array_values(array_diff(['x', 'y', 'z'], [$axis]));

        return $adjacent && $this->overlapsAlong($other, $crossAxes[0]) && $this->overlapsAlong($other, $crossAxes[1]);
    }

    private function overlapsAlong(self $other, string $axis): bool
    {
        return $this->overlapLength($other, $axis) > self::EPSILON;
    }

    private function overlapLength(self $other, string $axis): float
    {
        return min($this->end($axis), $other->end($axis)) - max($this->start($axis), $other->start($axis));
    }
}
