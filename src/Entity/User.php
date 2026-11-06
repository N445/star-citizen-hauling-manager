<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity('email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ADMIN_ROLE = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $registeredAt;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    public function __construct(
        #[ORM\Column(length: 180, unique: true)]
        private string $email,
    ) {
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->email;
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function changeEmail(string $email): void
    {
        $this->email = mb_strtolower(trim($email));
    }

    public function grantAdmin(): void
    {
        $this->roles = array_values(array_unique([...$this->roles, self::ADMIN_ROLE]));
    }

    public function revokeAdmin(): void
    {
        $this->roles = array_values(array_diff($this->roles, [self::ADMIN_ROLE]));
    }

    public function isAdmin(): bool
    {
        return \in_array(self::ADMIN_ROLE, $this->roles, true);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function registeredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
    }
}
