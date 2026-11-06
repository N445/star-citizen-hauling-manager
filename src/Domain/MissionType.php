<?php

declare(strict_types=1);

namespace App\Domain;

enum MissionType: string
{
    case Direct = 'direct';
    case MultipleDropOff = 'multipleDropOff';
    case MultiplePickUp = 'multiplePickUp';
    case FreeShopping = 'freeShopping';
}
