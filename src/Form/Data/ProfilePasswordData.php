<?php

declare(strict_types=1);

namespace App\Form\Data;

use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfilePasswordData
{
    #[SecurityAssert\UserPassword(message: 'profile.current_password.invalid')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank(message: 'registration.password.blank')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'registration.password.too_short', maxMessage: 'registration.password.too_long')]
    public ?string $newPassword = null;
}
