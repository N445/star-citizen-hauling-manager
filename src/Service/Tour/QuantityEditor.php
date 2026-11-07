<?php

declare(strict_types=1);

namespace App\Service\Tour;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\Tour;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Quantités réelles saisies en route. L'ordre des arrêts n'est jamais touché : seuls la capacité et le chargement
 * de la soute utilisent ces valeurs à la place de l'estimation de la mission.
 */
final readonly class QuantityEditor
{
    public const int MAXIMUM = 10000;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws QuantityException
     */
    public function setDelivery(Tour $tour, int $deliveryId, int $quantity): void
    {
        $delivery = $tour->delivery($deliveryId);
        $this->assertValid($tour, $delivery->mission(), $quantity, $delivery);
        $this->adjust($delivery, $quantity);
        $this->entityManager->flush();
    }

    /**
     * @param ?int $quantity null efface la saisie de ce lieu
     *
     * @throws QuantityException
     */
    public function setPickup(Tour $tour, int $missionId, string $location, ?int $quantity): void
    {
        $mission = $tour->mission($missionId);

        if (!\in_array($location, $mission->pickups(), true)) {
            throw new \OutOfBoundsException(sprintf('Lieu de ramassage « %s » inconnu pour la mission %d.', $location, $missionId));
        }

        if (MissionType::Direct === $mission->type()) {
            $this->setDelivery($tour, $mission->deliveries()->first()->id(), $quantity ?? $mission->deliveries()->first()->quantity());

            return;
        }

        if (MissionType::MultiplePickUp !== $mission->type()) {
            null !== $quantity && $this->assertValid($tour, $mission, $quantity);
            $mission->recordPickupQuantity($location, $quantity);
            $this->entityManager->flush();

            return;
        }

        $this->setSplitPickup($tour, $mission, $location, $quantity);
        $this->entityManager->flush();
    }

    /**
     * Plusieurs ramassages pour une seule livraison : une fois tous les lieux saisis, leur somme devient la quantité livrée.
     */
    private function setSplitPickup(Tour $tour, Mission $mission, string $location, ?int $quantity): void
    {
        $recorded = $mission->pickupQuantities();

        if (null === $quantity) {
            unset($recorded[$location]);
        } else {
            $recorded[$location] = $quantity;
        }

        $delivery = $mission->deliveries()->first();
        $complete = [] === array_diff($mission->pickups(), array_keys($recorded));
        $total = $complete ? array_sum(array_intersect_key($recorded, array_flip($mission->pickups()))) : null;

        if (null !== $total) {
            $this->assertValid($tour, $mission, $total, $delivery);
        } elseif (null !== $quantity) {
            $this->assertValid($tour, $mission, $quantity, $delivery);
        }

        $mission->recordPickupQuantity($location, $quantity);

        if (null !== $total) {
            $this->adjust($delivery, $total);
        } elseif ($delivery->hasActualQuantity()) {
            $delivery->adjustQuantity(null);
            $mission->quantitiesChanged();
        }
    }

    private function adjust(Delivery $delivery, int $quantity): void
    {
        if ($delivery->effectiveQuantity() === $quantity) {
            return;
        }

        $delivery->adjustQuantity($quantity === $delivery->quantity() ? null : $quantity);
        $delivery->mission()->quantitiesChanged();
    }

    private function assertValid(Tour $tour, Mission $mission, int $quantity, ?Delivery $changing = null): void
    {
        if ($quantity < 0 || $quantity > self::MAXIMUM) {
            throw new QuantityException('quantity.error.range', ['maximum' => self::MAXIMUM]);
        }

        $others = $mission->effectiveQuantity() - ($changing?->effectiveQuantity() ?? 0);

        if (null !== $changing && $others + $quantity > $tour->capacity()) {
            throw new QuantityException('quantity.error.exceeds_capacity', ['quantity' => $others + $quantity, 'capacity' => $tour->capacity()]);
        }

        if (null === $changing && $quantity > $tour->capacity()) {
            throw new QuantityException('quantity.error.exceeds_capacity', ['quantity' => $quantity, 'capacity' => $tour->capacity()]);
        }
    }
}
