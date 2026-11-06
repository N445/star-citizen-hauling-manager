<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Lieu de l'arbre système → planète → lieu → lune… ; un lieu sans parent est un système.
 */
#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\UniqueConstraint(fields: ['parent', 'name'])]
#[UniqueEntity(fields: ['parent', 'name'], ignoreNull: false, errorPath: 'name')]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Location> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $children;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Location $parent = null,
    ) {
        $this->name = trim($name);
        $this->children = new ArrayCollection();
    }

    public function rename(string $name): void
    {
        $this->name = trim($name);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    public function isSystem(): bool
    {
        return null === $this->parent;
    }

    /** @return list<Location> */
    public function children(): array
    {
        return array_values($this->children->toArray());
    }

    /** @return list<Location> du système jusqu'à ce lieu compris */
    public function path(): array
    {
        return [...($this->parent?->path() ?? []), $this];
    }

    public function __toString(): string
    {
        return implode(' › ', array_map(static fn (self $location): string => $location->name, $this->path()));
    }
}
