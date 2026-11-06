<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: 'email', message: 'registration.email.taken', entityClass: User::class, errorPath: 'email')]
final class RegistrationData
{
    #[Assert\NotBlank(message: 'registration.email.blank')]
    #[Assert\Email(message: 'registration.email.invalid')]
    #[Assert\Length(max: 180, maxMessage: 'registration.email.too_long')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'registration.password.blank')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'registration.password.too_short', maxMessage: 'registration.password.too_long')]
    public ?string $plainPassword = null;

    public function normalizedEmail(): string
    {
        return mb_strtolower(trim((string) $this->email));
    }
}
