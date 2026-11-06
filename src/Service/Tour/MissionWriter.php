<?php

declare(strict_types=1);

namespace App\Service\Tour;

use App\Entity\Mission;
use App\Entity\Tour;
use App\Form\Data\MissionData;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MissionWriter
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Tour $tour, ?Mission $mission, MissionData $data): void
    {
        if (null === $mission) {
            $tour->addMission($data->toMission());
            $this->entityManager->flush();

            return;
        }

        $data->applyTo($mission);
        $this->entityManager->flush();
    }

    public function remove(Mission $mission): void
    {
        $mission->tour()->removeMission($mission);
        $this->entityManager->flush();
    }
}
