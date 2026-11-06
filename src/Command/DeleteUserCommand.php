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

#[AsCommand('app:user:delete', 'Supprime un compte et tout ce qui y est rattaché (tournées, missions, historique)')]
final readonly class DeleteUserCommand
{
    public function __construct(
        private UserAccounts $userAccounts,
        private AccountRules $rules,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('E-mail du compte')] ?string $email = null,
        #[Option('Supprime sans demander de confirmation')] bool $force = false,
    ): int {
        try {
            $user = $this->rules->existingUser($email ?? $io->ask('E-mail du compte', null, fn (?string $answer): string => $this->rules->existingUser($answer)->email()));
        } catch (InvalidAccountException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Compte' => $user->email()],
            ['Rôles' => implode(', ', $user->getRoles())],
            ['Inscrit le' => $user->registeredAt()->format('d/m/Y H:i')],
            ['Tournées' => sprintf('%d (supprimées avec leurs missions)', $this->userAccounts->ownedTourCount($user))],
        );

        if (!$force && !$io->confirm('Supprimer ce compte ?', false)) {
            $io->warning('Suppression annulée.');

            return Command::FAILURE;
        }

        $this->userAccounts->delete($user);
        $io->success(sprintf('Compte %s supprimé.', $user->email()));

        return Command::SUCCESS;
    }
}
