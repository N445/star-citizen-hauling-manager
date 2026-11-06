<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ScCargo\ShipImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:ships:import', 'Importe les vaisseaux et leur grille cargo depuis sc-cargo.space')]
final readonly class ImportShipsCommand
{
    public function __construct(private ShipImporter $shipImporter)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $count = $this->shipImporter->import();
        $io->success(sprintf('%d vaisseaux importés depuis sc-cargo.space.', $count));

        return Command::SUCCESS;
    }
}
