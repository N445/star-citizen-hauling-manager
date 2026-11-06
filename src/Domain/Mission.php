<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Mission
{
    /** Largest crate size (SCU) allowed for this mission, always one of Crate::SIZES. */
    public int $maxContainerSize;

    /**
     * @param list<CargoLot> $lots
     * @param int            $maxContainerSize 1..32; a value between two valid sizes is rounded down
     *                                         (20 gives 16), a value outside 1..32 is refused
     *
     * @throws \InvalidArgumentException when $maxContainerSize is outside 1..32
     */
    public function __construct(
        public string $id,
        public string $label,
        public int $reward,
        public array $lots,
        public MissionType $type = MissionType::Direct,
        int $maxContainerSize = 32,
    ) {
        if ($maxContainerSize < 1 || $maxContainerSize > 32) {
            throw new \InvalidArgumentException('La taille maximale de caisse doit être comprise entre 1 et 32 SCU.');
        }
        $this->maxContainerSize = max(array_filter(Crate::SIZES, static fn (int $size): bool => $size <= $maxContainerSize));
    }

    public function totalScu(): int
    {
        return array_sum(array_map(static fn (CargoLot $lot): int => $lot->scu, $this->lots));
    }

    /**
     * Checks that the lots are consistent with the mission type.
     *
     * @throws \InvalidArgumentException French message describing the inconsistency
     */
    public function validate(): void
    {
        if ([] === $this->lots) {
            throw new \InvalidArgumentException(sprintf('La mission "%s" ne contient aucun lot.', $this->label));
        }

        $pickups = $this->distinctIds(static fn (CargoLot $l): ?string => $l->pickup->id);
        $drops = $this->distinctIds(static fn (CargoLot $l): ?string => $l->dropoff?->id);
        $withoutDrop = count(array_filter($this->lots, static fn (CargoLot $l): bool => null === $l->dropoff));

        switch ($this->type) {
            case MissionType::Direct:
                $this->require(1 === count($pickups) && 1 === count($drops) && 0 === $withoutDrop, 'une mission directe a un seul lieu de ramassage et un seul lieu de livraison');
                break;
            case MissionType::MultipleDropOff:
                $this->require(1 === count($pickups) && 0 === $withoutDrop, 'une mission à livraisons multiples a un seul lieu de ramassage et un lieu de livraison par lot');
                break;
            case MissionType::MultiplePickUp:
                $this->require(1 === count($drops) && 0 === $withoutDrop, 'une mission à ramassages multiples a un seul lieu de livraison');
                break;
            case MissionType::FreeShopping:
                $this->require(0 === $withoutDrop || count($this->lots) === $withoutDrop, 'les achats libres ont soit une destination commune pour tous les lots, soit aucune');
                $this->require(count($drops) <= 1, 'les achats libres ont au plus une destination');
                break;
        }
    }

    private function require(bool $condition, string $rule): void
    {
        if (!$condition) {
            throw new \InvalidArgumentException(sprintf('Mission "%s" incohérente : %s.', $this->label, $rule));
        }
    }

    /**
     * @param callable(CargoLot): ?string $get
     *
     * @return list<string>
     */
    private function distinctIds(callable $get): array
    {
        return array_values(array_unique(array_filter(array_map($get, $this->lots), static fn (?string $id): bool => null !== $id)));
    }
}
