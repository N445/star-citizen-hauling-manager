<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommodityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CommodityRepository::class)]
#[UniqueEntity(fields: 'name')]
class Commodity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 80, unique: true)]
        private string $name,
        #[ORM\Column(length: 7)]
        private string $color,
    ) {
    }

    public function rename(string $name): void
    {
        $this->name = trim($name);
    }

    public function recolor(string $color): void
    {
        $this->color = mb_strtolower($color);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function color(): string
    {
        return $this->color;
    }
}
