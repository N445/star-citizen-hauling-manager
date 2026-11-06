<?php

declare(strict_types=1);

namespace App\Service\Cargo;

final readonly class Spot
{
    public function __construct(
        public CargoBay $bay,
        public int $offsetX,
        public int $offsetY,
        public int $offsetZ,
        public int $width,
        public int $height,
        public int $length,
    ) {
    }

    public function overlaps(self $other): bool
    {
        return $this->overlapsAlong($other, 'x') && $this->overlapsAlong($other, 'y') && $this->overlapsAlong($other, 'z');
    }

    public function overlapsAlong(self $other, string $axis): bool
    {
        [$start, $end] = $this->span($axis);
        [$otherStart, $otherEnd] = $other->span($axis);

        return $start < $otherEnd && $otherStart < $end;
    }

    /**
     * @return array{float, float}
     */
    public function span(string $axis): array
    {
        return match ($axis) {
            'x' => [$this->x(), $this->x() + $this->width],
            'y' => [$this->y(), $this->y() + $this->height],
            'z' => [$this->z(), $this->z() + $this->length],
        };
    }

    public function x(): float
    {
        return $this->bay->x + $this->offsetX;
    }

    public function y(): float
    {
        return $this->bay->y + $this->offsetY;
    }

    public function z(): float
    {
        return $this->bay->z + $this->offsetZ;
    }
}
