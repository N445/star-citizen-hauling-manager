<?php

declare(strict_types=1);

namespace App\Service\ScCargo;

use App\Entity\Ship;
use App\Repository\ShipRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ShipImporter
{
    public function __construct(
        private ScCargoBundleFetcher $bundleFetcher,
        private ScCargoShipParser $shipParser,
        private ShipRepository $shipRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function import(): int
    {
        $bundle = $this->bundleFetcher->fetch();
        $ships = $this->shipParser->parse($bundle);

        foreach ($ships as $ship) {
            $this->upsert($ship);
        }

        $this->entityManager->flush();

        return \count($ships);
    }

    private function upsert(Ship $imported): void
    {
        $existing = $this->shipRepository->findSameAs($imported);

        if (null === $existing) {
            $this->entityManager->persist($imported);

            return;
        }

        $existing->updateFrom($imported);
    }
}
