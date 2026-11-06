<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class PlacedCrate
{
    /**
     * @param int  $orientation index into Crate::orientations()
     * @param bool $kept        crate never dropped (lot without destination): it stays aboard until the last
     *                          stop, $dropStop is then the last stop index and toDropAt() does not list it
     */
    public function __construct(
        public Crate $crate,
        public int $x,
        public int $y,
        public int $z,
        public int $width,
        public int $length,
        public int $height,
        public int $pickupStop,
        public int $dropStop,
        public int $blockIndex = 0,
        public int $orientation = 0,
        public bool $kept = false,
    ) {
    }
}
