<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Changer l'email ou le mot de passe modifie l'identité vérifiée par la session : on reconnecte aussitôt,
 * sinon Symfony déconnecterait l'utilisateur à la requête suivante.
 */
final readonly class ProfileEditor
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private UserAccounts $userAccounts,
        private Security $security,
    ) {
    }

    public function changeEmail(User $user, string $email): void
    {
        $user->changeEmail($email);
        $this->saveAndStayLoggedIn($user);
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->changePassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->saveAndStayLoggedIn($user);
    }

    public function deleteAccount(User $user): void
    {
        $this->security->logout(false);
        $this->userAccounts->delete($user);
    }

    private function saveAndStayLoggedIn(User $user): void
    {
        $this->entityManager->flush();
        $this->security->login($user, 'form_login', 'main');
    }
}
