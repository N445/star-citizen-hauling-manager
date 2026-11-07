<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RouteReportRepository;
use App\Repository\UserRepository;
use App\Service\Report\ReportReplay;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:reports:replay', 'Recharge l\'instantané d\'un signalement de route en tournée, pour la rejouer à l\'écran')]
final readonly class ReplayReportCommand
{
    public function __construct(
        private RouteReportRepository $reports,
        private UserRepository $users,
        private ReportReplay $replay,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Identifiant du signalement')] int $id,
        #[Option('E-mail du compte qui reçoit la tournée (sans propriétaire par défaut : `app:tours:claim` la rattache ensuite)')] ?string $user = null,
    ): int {
        $report = $this->reports->find($id) ?? throw new InvalidArgumentException(sprintf('Aucun signalement n°%d.', $id));
        $owner = null === $user ? null : ($this->users->findOneByEmail($user) ?? throw new InvalidOptionException(sprintf('Aucun compte pour « %s ».', $user)));
        $tour = $this->replay->replay($report, $owner);

        $io->success(sprintf('Tournée n°%d créée (mode de calcul du signalement : %s).', $tour->id(), $report->snapshot()['tour']['mode']));

        return Command::SUCCESS;
    }
}
