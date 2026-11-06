<?php

declare(strict_types=1);

namespace App\Entity;

enum DoorSide: string
{
    case Rear = 'rear';
    case Front = 'front';
    case Left = 'left';
    case Right = 'right';
    case Top = 'top';
    case Bottom = 'bottom';

    public function label(): string
    {
        return 'door_side.'.$this->value;
    }

    /**
     * Axe de la grille perpendiculaire à la porte (x largeur, y hauteur, z longueur ; z = 0 supposé à l'avant).
     */
    public function axis(): string
    {
        return match ($this) {
            self::Rear, self::Front => 'z',
            self::Left, self::Right => 'x',
            self::Top, self::Bottom => 'y',
        };
    }

    public function isAtMaximum(): bool
    {
        return \in_array($this, [self::Rear, self::Right, self::Top], true);
    }
}
