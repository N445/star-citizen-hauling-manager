<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Account\AccountRules;
use App\Service\Account\InvalidAccountException;
use App\Service\Account\UserAccounts;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:user:create', 'Crée un compte utilisateur (questions interactives si les valeurs manquent)')]
final readonly class CreateUserCommand
{
    public function __construct(
        private UserAccounts $userAccounts,
        private AccountRules $rules,
        private PasswordPrompt $passwordPrompt,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('E-mail du compte')] ?string $email = null,
        #[Option('Mot de passe (sinon demandé en saisie masquée)')] ?string $password = null,
        #[Option('Administrateur (ROLE_ADMIN) ; --no-admin pour un simple utilisateur')] ?bool $admin = null,
    ): int {
        try {
            $user = $this->userAccounts->create(
                $email ?? $io->ask('E-mail', null, $this->rules->validEmail(...)),
                $password ?? $this->passwordPrompt->askNew($io, 'Mot de passe (8 caractères min.)'),
                $admin ?? $io->confirm('Administrateur ?', false),
            );
        } catch (InvalidAccountException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Compte %s créé (%s).', $user->email(), implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
