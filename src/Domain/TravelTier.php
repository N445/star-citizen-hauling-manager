<?php

declare(strict_types=1);

namespace App\Domain;

enum TravelTier: string
{
    case Local = 'local';
    case Planetary = 'planetary';
    case Stellar = 'stellar';
    case Interstellar = 'interstellar';

    public function cost(): int
    {
        return match ($this) {
            self::Local => 0,
            self::Planetary => 1,
            self::Stellar => 3,
            self::Interstellar => 10,
        };
    }
}
