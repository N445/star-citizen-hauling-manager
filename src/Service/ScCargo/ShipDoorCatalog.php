<?php

declare(strict_types=1);

namespace App\Service\ScCargo;

use App\Entity\DoorSide;
use App\Entity\Ship;

/**
 * Portes cargo connues, par slug de vaisseau (absentes de sc-cargo.space). Tout vaisseau absent de la liste
 * garde la porte arrière par défaut ; l'admin permet de corriger.
 */
final class ShipDoorCatalog
{
    /** @var array<string, array{main: DoorSide, doors: list<DoorSide>}> */
    private const array KNOWN = [
        'drake-caterpillar' => ['main' => DoorSide::Left, 'doors' => [DoorSide::Left, DoorSide::Right]],
    ];

    public function applyTo(Ship $ship): bool
    {
        $known = self::KNOWN[$ship->slug()] ?? null;

        if (null === $known) {
            return false;
        }

        $ship->changeDoors($known['doors'], $known['main']);

        return true;
    }
}
