<?php

declare(strict_types=1);

namespace App\Form\Data;

use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

final class AccountDeletionData
{
    #[SecurityAssert\UserPassword(message: 'profile.current_password.invalid')]
    public ?string $currentPassword = null;

    #[Assert\IsTrue(message: 'profile.delete.confirmation_required')]
    public bool $confirmed = false;
}
