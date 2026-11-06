<?php

declare(strict_types=1);

namespace App\Domain;

enum DoorSide: string
{
    case Rear = 'rear';
    case Front = 'front';
    case Left = 'left';
    case Right = 'right';
}
