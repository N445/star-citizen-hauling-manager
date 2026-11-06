<?php

declare(strict_types=1);

namespace App\Domain;

/** A crate position fixed by the user; orientation is an index into Crate::orientations(). */
final readonly class Pin
{
    public function __construct(
        public string $crateId,
        public int $x,
        public int $y,
        public int $z,
        public int $orientation = 0,
    ) {
    }
}
