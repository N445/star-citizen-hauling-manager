<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class CargoBlock
{
    public function __construct(
        public int $x,
        public int $y,
        public int $z,
        public int $width,
        public int $length,
        public int $height,
        public ?int $maxSize = null,
    ) {
        if ($width < 1 || $length < 1 || $height < 1) {
            throw new \InvalidArgumentException('Block dimensions must be positive.');
        }
    }

    public function volume(): int
    {
        return $this->width * $this->length * $this->height;
    }

    public function contains(int $x, int $y, int $z): bool
    {
        return $x >= $this->x && $x < $this->x + $this->width
            && $y >= $this->y && $y < $this->y + $this->length
            && $z >= $this->z && $z < $this->z + $this->height;
    }

    public function accepts(Crate $crate): bool
    {
        if (null !== $this->maxSize && $crate->sizeScu > $this->maxSize) {
            return false;
        }
        foreach ($crate->orientations() as [$w, $l, $h]) {
            if ($w <= $this->width && $l <= $this->length && $h <= $this->height) {
                return true;
            }
        }

        return false;
    }
}
