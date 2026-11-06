<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Location
{
    /**
     * @param string      $planet main parent body (a moon or a Lagrange point belongs to its planet)
     * @param string|null $body   moon name or Lagrange point name, for types Moon and Lagrange
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $system,
        public string $planet,
        public LocationType $type,
        public ?string $body = null,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }
}
