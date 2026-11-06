<?php

declare(strict_types=1);

namespace App\Service\Tour;

use App\Entity\Tour;
use App\Entity\User;
use App\Form\Data\TourData;
use App\Repository\ShipRepository;
use App\Repository\TourRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TourWriter
{
    public function __construct(
        private ShipRepository $shipRepository,
        private TourRepository $tourRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(TourData $data, User $owner): Tour
    {
        $tour = $this->newTour($data);
        $tour->assignTo($owner);

        return $this->save($tour);
    }

    /**
     * Un visiteur sans compte n'a qu'une tournée : la nouvelle remplace l'ancienne.
     */
    public function createForGuest(TourData $data, string $guestToken): Tour
    {
        $previous = $this->tourRepository->findOneOfGuest($guestToken);

        if (null !== $previous) {
            $this->entityManager->remove($previous);
            $this->entityManager->flush();
        }

        $tour = $this->newTour($data);
        $tour->entrustToGuest($guestToken);

        return $this->save($tour);
    }

    /**
     * @param list<string> $locations
     */
    public function reorderStops(Tour $tour, array $locations): void
    {
        $tour->reorderStops($locations);
        $this->entityManager->flush();
    }

    private function newTour(TourData $data): Tour
    {
        $tour = new Tour($data->name, $data->shipName, $data->capacity, $this->shipRepository->findOneByFullName($data->shipName), $data->startLocation);
        $tour->overrideDoors($data->doorSides);

        return $tour;
    }

    private function save(Tour $tour): Tour
    {
        $this->entityManager->persist($tour);
        $this->entityManager->flush();

        return $tour;
    }

    public function resetStopOrder(Tour $tour): void
    {
        $tour->resetStopOrder();
        $this->entityManager->flush();
    }

    public function update(Tour $tour, TourData $data): void
    {
        $tour->update($data->name, $data->shipName, $data->capacity, $this->shipRepository->findOneByFullName($data->shipName), $data->startLocation);
        $tour->overrideDoors($data->doorSides);
        $this->entityManager->flush();
    }
}
