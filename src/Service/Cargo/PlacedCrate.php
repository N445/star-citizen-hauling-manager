<?php

declare(strict_types=1);

namespace App\Service\Cargo;

use App\Entity\Delivery;

final readonly class PlacedCrate
{
    public function __construct(
        public string $id,
        public Delivery $delivery,
        public int $size,
        public Spot $spot,
        public string $pickupLocation,
        public int $pickupStep,
        public ?int $dropStep,
        public bool $stackedOnEarlierDelivery = false,
        public bool $placed = false,
        public bool $manual = false,
    ) {
    }

    public function withStacking(bool $stackedOnEarlierDelivery): self
    {
        return new self($this->id, $this->delivery, $this->size, $this->spot, $this->pickupLocation, $this->pickupStep, $this->dropStep, $stackedOnEarlierDelivery, $this->placed, $this->manual);
    }

    /**
     * @return array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int, manual?: true, placed?: true}
     */
    public function toPlacement(): array
    {
        return [
            ...($this->manual ? ['manual' => true] : []),
            ...($this->placed ? ['placed' => true] : []),
            'delivery' => $this->delivery->id(),
            'size' => $this->size,
            'x' => $this->spot->x(),
            'y' => $this->spot->y(),
            'z' => $this->spot->z(),
            'width' => $this->spot->width,
            'height' => $this->spot->height,
            'length' => $this->spot->length,
        ];
    }

    public function isVisibleAt(int $step): bool
    {
        return !$this->delivery->isDelivered() && $this->pickupStep <= $step && ($this->dropStep ?? PHP_INT_MAX) >= $step;
    }

    public function isPhantom(): bool
    {
        return !$this->delivery->mission()->isPickedUpAt($this->pickupLocation);
    }

    public function isUnloadingAt(int $step): bool
    {
        return $this->dropStep === $step;
    }

    public function isLoadingAt(int $step): bool
    {
        return $this->pickupStep === $step;
    }

    public function overlaps(self $other): bool
    {
        return $this->spot->overlaps($other->spot);
    }
}
