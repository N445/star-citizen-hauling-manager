<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: 'email', message: 'registration.email.taken', entityClass: User::class, errorPath: 'email')]
final class ProfileEmailData
{
    #[Assert\NotBlank(message: 'registration.email.blank')]
    #[Assert\Email(message: 'registration.email.invalid')]
    #[Assert\Length(max: 180, maxMessage: 'registration.email.too_long')]
    public ?string $email = null;

    #[SecurityAssert\UserPassword(message: 'profile.current_password.invalid')]
    public ?string $currentPassword = null;
}
