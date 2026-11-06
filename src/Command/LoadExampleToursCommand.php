<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Example\ExampleTourLoader;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:examples:load', 'Crée (ou recrée à neuf) les tournées d\'exemple, de la plus simple à la plus complexe')]
final readonly class LoadExampleToursCommand
{
    public function __construct(
        private ExampleTourLoader $exampleTourLoader,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Numéro de l\'exemple à recréer seul (tous par défaut)')] ?int $number = null,
        #[Option('E-mail du compte qui reçoit les exemples (sans propriétaire par défaut)')] ?string $user = null,
    ): int {
        $count = $this->exampleTourLoader->load($number, $this->ownerOf($user));
        $io->success(sprintf('%d tournées d\'exemple créées.', $count));

        return Command::SUCCESS;
    }

    private function ownerOf(?string $email): ?User
    {
        if (null === $email) {
            return null;
        }

        return $this->userRepository->findOneByEmail($email)
            ?? throw new InvalidOptionException(sprintf('Aucun compte pour « %s ».', $email));
    }
}
