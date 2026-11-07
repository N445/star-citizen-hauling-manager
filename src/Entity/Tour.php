<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TourRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TourRepository::class)]
class Tour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var list<string>|null arrêts posés à la main (hors départ) ; null = parcours calculé */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $stopOrder = null;

    /** @var list<string>|null portes choisies par le joueur pour cette tournée ; null = porte principale du vaisseau */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $doorSides = null;

    /** @var Collection<int, Mission> */
    #[ORM\OneToMany(targetEntity: Mission::class, mappedBy: 'tour', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $missions;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $owner = null;

    /** tournée d'un visiteur sans compte, retrouvée grâce au jeton de son navigateur */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $guestToken = null;

    /**
     * @param ?Ship $ship null quand le joueur a saisi un vaisseau absent du catalogue : pas de grille connue
     */
    public function __construct(
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 120)]
        private string $shipName,
        #[ORM\Column]
        private int $capacity,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?Ship $ship,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $startLocation = null,
    ) {
        $this->missions = new ArrayCollection();
    }

    public function update(string $name, string $shipName, int $capacity, ?Ship $ship, ?string $startLocation): void
    {
        $this->name = $name;
        $this->shipName = $shipName;
        $this->capacity = $capacity;
        $this->ship = $ship;
        $this->startLocation = $startLocation;
    }

    /**
     * @param list<DoorSide> $doors
     */
    public function overrideDoors(array $doors): void
    {
        $values = array_values(array_unique(array_map(static fn (DoorSide $door): string => $door->value, $doors)));
        $default = array_map(static fn (DoorSide $door): string => $door->value, $this->defaultDoors());
        sort($values);
        sort($default);

        $this->doorSides = [] === $values || $values === $default ? null : array_values(array_unique(array_map(static fn (DoorSide $door): string => $door->value, $doors)));
    }

    /**
     * @return list<DoorSide>
     */
    public function doorSides(): array
    {
        return null === $this->doorSides ? $this->defaultDoors() : array_map(DoorSide::from(...), $this->doorSides);
    }

    /**
     * @return list<DoorSide>
     */
    public function defaultDoors(): array
    {
        return [$this->ship?->mainDoor() ?? DoorSide::Rear];
    }

    public function hasDoorOverride(): bool
    {
        return null !== $this->doorSides;
    }

    /**
     * @param list<string> $locations
     */
    public function reorderStops(array $locations): void
    {
        $this->stopOrder = $locations;
    }

    public function resetStopOrder(): void
    {
        $this->stopOrder = null;
    }

    /**
     * @return list<string>|null
     */
    public function stopOrder(): ?array
    {
        return $this->stopOrder;
    }

    public function assignTo(User $owner): void
    {
        $this->owner = $owner;
        $this->guestToken = null;
    }

    public function entrustToGuest(string $guestToken): void
    {
        $this->guestToken = $guestToken;
    }

    public function isGuestTour(): bool
    {
        return null === $this->owner && null !== $this->guestToken;
    }

    public function belongsToGuest(?string $guestToken): bool
    {
        return null === $this->owner && null !== $guestToken && $this->guestToken === $guestToken;
    }

    public function isOwnedBy(User $user): bool
    {
        return null !== $this->owner && $this->owner->id() === $user->id();
    }

    public function owner(): ?User
    {
        return $this->owner;
    }

    public function addMission(Mission $mission): void
    {
        $mission->attachTo($this);
        $this->missions->add($mission);
    }

    public function removeMission(Mission $mission): void
    {
        $this->missions->removeElement($mission);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function shipName(): string
    {
        return $this->shipName;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function ship(): ?Ship
    {
        return $this->ship;
    }

    public function startLocation(): ?string
    {
        return $this->startLocation;
    }

    public function mission(int $missionId): Mission
    {
        return $this->missions->findFirst(static fn (int $key, Mission $mission): bool => $mission->id() === $missionId)
            ?? throw new \OutOfBoundsException(sprintf('Mission %d introuvable dans la tournée.', $missionId));
    }

    public function delivery(int $deliveryId): Delivery
    {
        $deliveries = array_merge(...array_map(static fn (Mission $mission): array => $mission->deliveries()->getValues(), $this->missions->getValues()));
        $matching = array_filter($deliveries, static fn (Delivery $delivery): bool => $delivery->id() === $deliveryId);

        return reset($matching) ?: throw new \OutOfBoundsException(sprintf('Livraison %d introuvable dans la tournée.', $deliveryId));
    }

    public function missionLabel(Mission $mission): string
    {
        return $mission->name() ?? sprintf('Mission %d', $this->missions->indexOf($mission) + 1);
    }

    /**
     * @return Collection<int, Mission>
     */
    public function missions(): Collection
    {
        return $this->missions;
    }

    public function totalReward(): int
    {
        return array_sum($this->missions->map(static fn (Mission $mission): int => $mission->reward())->toArray());
    }

    public function totalQuantity(): int
    {
        return array_sum($this->missions->map(static fn (Mission $mission): int => $mission->effectiveQuantity())->toArray());
    }
}
