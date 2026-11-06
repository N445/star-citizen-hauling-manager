<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TourRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:tours:claim', 'Attribue les tournées sans propriétaire à un utilisateur')]
final readonly class ClaimToursCommand
{
    public function __construct(
        private UserRepository $userRepository,
        private TourRepository $tourRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('E-mail du compte qui reçoit les tournées')] string $email): int
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (null === $user) {
            $io->error(sprintf('Aucun compte pour « %s ».', $email));

            return Command::FAILURE;
        }

        $orphans = $this->tourRepository->findOrphans();

        foreach ($orphans as $tour) {
            $tour->assignTo($user);
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d tournée(s) attribuée(s) à %s.', \count($orphans), $user->email()));

        return Command::SUCCESS;
    }
}
