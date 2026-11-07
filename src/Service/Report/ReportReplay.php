<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\Delivery;
use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\RouteReport;
use App\Entity\Ship;
use App\Entity\Tour;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Recharge l'instantané d'un signalement en tournée ordinaire (avancement, quantités réelles, ordre manuel et caisses
 * figées compris) pour la rejouer à l'écran. Le mode de calcul n'est pas stocké sur la tournée : il reste dans l'instantané.
 */
final readonly class ReportReplay
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function replay(RouteReport $report, ?User $owner): Tour
    {
        $snapshot = $report->snapshot();
        $data = $snapshot['tour'];
        $ship = null === $data['ship'] ? null : $this->entityManager->getRepository(Ship::class)->findOneBy(['slug' => $data['ship']['slug']]);
        $tour = new Tour(sprintf('Report #%d · %s', $report->id(), $data['name']), $data['shipName'], $data['capacity'], $ship, $data['startLocation']);

        if ($data['doorsOverridden']) {
            $tour->overrideDoors(array_map(DoorSide::from(...), $data['doors']));
        }

        $missions = array_map(fn (array $mission): Mission => $this->mission($mission), $snapshot['missions']);

        foreach ($missions as $mission) {
            $tour->addMission($mission);
        }

        null === $owner || $tour->assignTo($owner);
        $this->entityManager->persist($tour);
        $this->entityManager->flush();

        $this->restoreCargo($snapshot['missions'], $missions);
        null === $data['stopOrder'] || $tour->reorderStops($data['stopOrder']);
        $this->entityManager->flush();

        return $tour;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mission(array $data): Mission
    {
        $deliveries = array_map(static function (array $delivery): Delivery {
            $replayed = new Delivery($delivery['dropoff'], $delivery['resource'], $delivery['quantity']);
            $replayed->adjustQuantity($delivery['actualQuantity']);
            !$delivery['delivered'] || $replayed->toggleDelivered();

            return $replayed;
        }, $data['deliveries']);

        $mission = new Mission($data['name'], $data['reward'], MissionType::from($data['type']), $data['pickups'], $deliveries);

        foreach ($data['pickedUpAt'] as $location) {
            $mission->togglePickupAt($location);
        }

        foreach ($data['pickupQuantities'] as $location => $quantity) {
            $mission->recordPickupQuantity((string) $location, $quantity);
        }

        return $mission;
    }

    /**
     * Les caisses figées désignent des livraisons par leur ancien identifiant : on les renvoie vers les nouvelles, dans l'ordre.
     *
     * @param list<array<string, mixed>> $snapshotMissions
     * @param list<Mission>              $missions
     */
    private function restoreCargo(array $snapshotMissions, array $missions): void
    {
        foreach ($snapshotMissions as $index => $data) {
            if (null === $data['cargoPlacement']) {
                continue;
            }

            $newIds = array_map(static fn (Delivery $delivery): ?int => $delivery->id(), $missions[$index]->deliveries()->getValues());
            $idMap = array_combine(array_column($data['deliveries'], 'id'), $newIds);
            $missions[$index]->freezeCargo(array_map(static fn (array $crate): array => [...$crate, 'delivery' => $idMap[$crate['delivery']] ?? $crate['delivery']], $data['cargoPlacement']));
        }
    }
}
