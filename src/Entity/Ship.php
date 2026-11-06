<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @phpstan-type CargoGrid array{name?: string, x: float, y: float, z: float, width: int, height: int, length: int, minSize?: int, maxSize?: int, preferHorizontal?: bool}
 * @phpstan-type CargoGroup array{name?: string, x: float, z: float, minSize?: int, grids: list<CargoGrid>}
 */
#[ORM\Entity(repositoryClass: ShipRepository::class)]
class Ship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $slug;

    /** @var list<string> côtés des portes cargo du vaisseau (absents de sc-cargo.space) */
    #[ORM\Column(type: Types::JSON)]
    private array $doorSides = [DoorSide::Rear->value];

    /** porte utilisée par défaut par une tournée ; toujours l'une des portes du vaisseau */
    #[ORM\Column(length: 10)]
    private string $mainDoor = DoorSide::Rear->value;

    /**
     * @param list<CargoGroup> $cargoGroups grille officielle de sc-cargo.space, axes source : x largeur, y hauteur, z longueur
     */
    public function __construct(
        #[ORM\Column(length: 60)]
        private string $manufacturer,
        #[ORM\Column(length: 80)]
        private string $name,
        #[ORM\Column]
        private int $capacity,
        #[ORM\Column(type: Types::JSON)]
        private array $cargoGroups,
    ) {
        $this->slug = (new AsciiSlugger())->slug($manufacturer.' '.$name)->lower()->toString();
    }

    public function updateFrom(self $imported): void
    {
        $this->manufacturer = $imported->manufacturer;
        $this->name = $imported->name;
        $this->capacity = $imported->capacity;
        $this->cargoGroups = $imported->cargoGroups;
    }

    /**
     * @param list<DoorSide> $doors
     */
    public function changeDoors(array $doors, ?DoorSide $main = null): void
    {
        $this->doorSides = array_values(array_unique(array_map(static fn (DoorSide $door): string => $door->value, $doors)));
        $this->mainDoor = null !== $main && \in_array($main->value, $this->doorSides, true) ? $main->value : ($this->doorSides[0] ?? DoorSide::Rear->value);
    }

    public function changeMainDoor(DoorSide $main): void
    {
        $this->changeDoors([...$this->doorSides(), $main], $main);
    }

    public function mainDoor(): DoorSide
    {
        return DoorSide::from($this->mainDoor);
    }

    /**
     * @return list<DoorSide>
     */
    public function doorSides(): array
    {
        return array_map(DoorSide::from(...), $this->doorSides);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function manufacturer(): string
    {
        return $this->manufacturer;
    }

    public function name(): string
    {
        return $this->manufacturer.' '.$this->name;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    /**
     * @return list<CargoGroup>
     */
    public function cargoGroups(): array
    {
        return $this->cargoGroups;
    }
}
