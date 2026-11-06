<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Form\Data\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserRegistration
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function register(RegistrationData $data): void
    {
        $user = new User($data->normalizedEmail());
        $user->changePassword($this->passwordHasher->hashPassword($user, (string) $data->plainPassword));
        $this->save($user);

        $this->security->login($user, 'form_login', 'main');
    }

    private function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
