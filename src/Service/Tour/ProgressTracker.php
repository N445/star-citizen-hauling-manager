<?php

declare(strict_types=1);

namespace App\Service\Tour;

use App\Entity\Mission;
use App\Entity\Tour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class ProgressTracker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function toggleDelivery(Tour $tour, int $deliveryId): void
    {
        $delivery = $tour->delivery($deliveryId);
        $delivery->toggleDelivered();
        $delivery->mission()->recordProgress($this->clock->now());
        $this->entityManager->flush();
    }

    /**
     * @param list<array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int}> $proposedPlacement
     */
    public function togglePickup(Tour $tour, int $missionId, string $location, array $proposedPlacement): void
    {
        $mission = $tour->mission($missionId);
        $mission->togglePickupAt($location);
        $this->freezeIfPickedUp($mission, $location, $proposedPlacement);
        $mission->recordProgress($this->clock->now());
        $this->entityManager->flush();
    }

    /**
     * @param list<array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int}> $proposedPlacement
     */
    private function freezeIfPickedUp(Mission $mission, string $location, array $proposedPlacement): void
    {
        if (!$mission->isPickedUpAt($location) || [] === $proposedPlacement || null !== $mission->frozenCargo()) {
            return;
        }

        $mission->freezeCargo($proposedPlacement);
    }
}
