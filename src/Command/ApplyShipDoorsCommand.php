<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ShipRepository;
use App\Service\ScCargo\ShipDoorCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:ships:doors', 'Applique les portes cargo connues aux vaisseaux en base (écrase les portes de ces vaisseaux)')]
final readonly class ApplyShipDoorsCommand
{
    public function __construct(
        private ShipRepository $shipRepository,
        private ShipDoorCatalog $catalog,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $count = 0;

        foreach ($this->shipRepository->findAllSorted() as $ship) {
            $count += (int) $this->catalog->applyTo($ship);
        }

        $this->entityManager->flush();
        $io->success(sprintf('Portes appliquées à %d vaisseau(x) ; les autres gardent la porte arrière.', $count));

        return Command::SUCCESS;
    }
}
