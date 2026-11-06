<?php

declare(strict_types=1);

namespace App\Service\Route;

use App\Entity\Delivery;

final readonly class Task
{
    private function __construct(
        public int $missionIndex,
        public string $location,
        public ?Delivery $delivery,
    ) {
    }

    public static function pickup(int $missionIndex, string $location): self
    {
        return new self($missionIndex, $location, null);
    }

    public static function delivery(int $missionIndex, Delivery $delivery): self
    {
        return new self($missionIndex, $delivery->dropoff(), $delivery);
    }

    public function isPickup(): bool
    {
        return null === $this->delivery;
    }
}
