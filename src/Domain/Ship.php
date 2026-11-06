<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Ship
{
    public function __construct(
        public string $name,
        public CargoGrid $grid,
        public string $slug = '',
        public string $manufacturer = '',
    ) {
    }

    public function capacity(): int
    {
        return $this->grid->capacity();
    }

    public function withDoor(DoorSide $door): self
    {
        return new self($this->name, $this->grid->withDoor($door), $this->slug, $this->manufacturer);
    }

    public function label(): string
    {
        return trim($this->manufacturer.' '.$this->name);
    }
}
