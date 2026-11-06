<?php

declare(strict_types=1);

namespace App\Entity;

enum MissionType: string
{
    case Direct = 'direct';
    case MultipleDropOff = 'multiple_drop_off';
    case MultiplePickUp = 'multiple_pick_up';

    public function label(): string
    {
        return 'mission_type.'.$this->value.'.label';
    }

    public function shortLabel(): string
    {
        return 'mission_type.'.$this->value.'.short';
    }

    public function accepts(int $pickupCount, int $dropoffCount): bool
    {
        return match ($this) {
            self::Direct => 1 === $pickupCount && 1 === $dropoffCount,
            self::MultipleDropOff => 1 === $pickupCount && $dropoffCount >= 2,
            self::MultiplePickUp => $pickupCount >= 2 && 1 === $dropoffCount,
        };
    }

    public function hasSeveralPickups(): bool
    {
        return self::MultiplePickUp === $this;
    }

    public function rule(): string
    {
        return 'mission_type.'.$this->value.'.rule';
    }
}
