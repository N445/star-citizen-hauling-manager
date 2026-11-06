<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class CrateTransfer
{
    public function __construct(
        public Crate $crate,
        public Location $pickup,
        public ?Location $dropoff,
    ) {
    }
}
