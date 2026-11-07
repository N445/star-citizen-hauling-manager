<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\User;
use App\Repository\ContactMessageRepository;
use App\Repository\TourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserAccounts
{
    public function __construct(
        private AccountRules $rules,
        private UserPasswordHasherInterface $passwordHasher,
        private TourRepository $tourRepository,
        private ContactMessageRepository $contactMessages,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(?string $email, ?string $plainPassword, bool $admin): User
    {
        $user = new User($this->rules->validEmail($email));
        $this->changePassword($user, $plainPassword);
        $this->changeAdmin($user, $admin);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function edit(User $user, ?string $email, ?string $plainPassword, ?bool $admin): void
    {
        $user->changeEmail($this->rules->validEmail($email ?? $user->email(), $user));
        $this->changePassword($user, $plainPassword);
        $this->changeAdmin($user, $admin ?? $user->isAdmin());
        $this->entityManager->flush();
    }

    /**
     * Supprimer un compte supprime tout : ses tournées, leurs missions, donc son historique. Seuls ses messages de contact
     * restent, rendus anonymes.
     */
    public function delete(User $user): void
    {
        $this->contactMessages->anonymizeAuthor($user);

        foreach ($this->tourRepository->findOwnedBy($user) as $tour) {
            $this->entityManager->remove($tour);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function ownedTourCount(User $user): int
    {
        return $this->tourRepository->count(['owner' => $user]);
    }

    private function changePassword(User $user, ?string $plainPassword): void
    {
        if (null === $plainPassword && '' !== $user->getPassword()) {
            return;
        }

        $user->changePassword($this->passwordHasher->hashPassword($user, $this->rules->validPassword($plainPassword)));
    }

    private function changeAdmin(User $user, bool $admin): void
    {
        if ($admin) {
            $user->grantAdmin();

            return;
        }

        $user->revokeAdmin();
    }
}
