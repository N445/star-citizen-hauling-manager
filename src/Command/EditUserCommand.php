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

#[AsCommand('app:user:edit', 'Modifie un compte : e-mail, mot de passe, rôle admin (Entrée = valeur inchangée)')]
final readonly class EditUserCommand
{
    public function __construct(
        private UserAccounts $userAccounts,
        private AccountRules $rules,
        private PasswordPrompt $passwordPrompt,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('E-mail actuel du compte')] ?string $email = null,
        #[Option('Nouvel e-mail')] ?string $newEmail = null,
        #[Option('Nouveau mot de passe')] ?string $password = null,
        #[Option('Donne (--admin) ou retire (--no-admin) le rôle administrateur')] ?bool $admin = null,
    ): int {
        try {
            $user = $this->rules->existingUser($email ?? $io->ask('E-mail du compte', null, fn (?string $answer): string => $this->rules->existingUser($answer)->email()));
            $this->userAccounts->edit(
                $user,
                $newEmail ?? $io->ask('Nouvel e-mail', $user->email(), fn (?string $answer): string => $this->rules->validEmail($answer, $user)),
                $password ?? $this->passwordPrompt->askNew($io, 'Nouveau mot de passe (Entrée = inchangé)', optional: true),
                $admin ?? $io->confirm('Administrateur ?', $user->isAdmin()),
            );
        } catch (InvalidAccountException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Compte %s mis à jour (%s).', $user->email(), implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
