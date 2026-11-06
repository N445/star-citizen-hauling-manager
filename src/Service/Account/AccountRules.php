<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AccountRules
{
    public const int PASSWORD_MIN_LENGTH = 8;

    public function __construct(
        private ValidatorInterface $validator,
        private UserRepository $userRepository,
    ) {
    }

    public function validEmail(?string $email, ?User $owner = null): string
    {
        $normalized = mb_strtolower(trim((string) $email));
        $this->assert($normalized, [
            new Assert\NotBlank(message: 'L’e-mail est obligatoire.'),
            new Assert\Email(message: 'E-mail invalide : {{ value }}.'),
            new Assert\Length(max: 180, maxMessage: 'L’e-mail dépasse 180 caractères.'),
        ]);
        $existing = $this->userRepository->findOneByEmail($normalized);

        if (null !== $existing && $existing !== $owner) {
            throw new InvalidAccountException(sprintf('Un compte existe déjà pour « %s ».', $normalized));
        }

        return $normalized;
    }

    public function validPassword(?string $password): string
    {
        $this->assert((string) $password, [
            new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
            new Assert\Length(min: self::PASSWORD_MIN_LENGTH, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
        ]);

        return (string) $password;
    }

    public function existingUser(?string $email): User
    {
        $normalized = mb_strtolower(trim((string) $email));

        return $this->userRepository->findOneByEmail($normalized)
            ?? throw new InvalidAccountException(sprintf('Aucun compte pour « %s ».', $normalized));
    }

    /**
     * @param list<Constraint> $constraints
     */
    private function assert(string $value, array $constraints): void
    {
        $violations = $this->validator->validate($value, $constraints);

        if (\count($violations) > 0) {
            throw new InvalidAccountException((string) $violations->get(0)->getMessage());
        }
    }
}
