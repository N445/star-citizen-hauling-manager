<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\DoorSide;
use App\Entity\Ship;

final readonly class ShipBlueprint
{
    /**
     * @param list<DoorSide> $doors
     */
    public function __construct(
        private string $key,
        private array $doors,
    ) {
    }

    public function build(): Ship
    {
        return ShipFixtures::build($this->key, $this->doors);
    }

    public function volume(): int
    {
        return ShipFixtures::volume($this->key);
    }

    public function label(): string
    {
        $doorLabels = implode('+', array_map(static fn (DoorSide $door): string => mb_strtolower($door->label()), $this->doors));
        $noun = 1 === \count($this->doors) ? 'porte' : 'portes';

        return sprintf('%s · %s %s', ShipFixtures::label($this->key), $noun, $doorLabels);
    }
}
