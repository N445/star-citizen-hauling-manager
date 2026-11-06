<?php

declare(strict_types=1);

namespace App\Domain;

enum LocationType: string
{
    case Surface = 'surface';
    case Orbital = 'orbital';
    case Moon = 'moon';
    case Lagrange = 'lagrange';
}
