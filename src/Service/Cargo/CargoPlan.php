<?php

declare(strict_types=1);

namespace App\Service\Cargo;

final readonly class CargoPlan
{
    /**
     * @param list<PlacedCrate> $crates
     */
    public function __construct(
        public array $crates,
        public int $currentStep,
        public int $unplacedCount,
    ) {
    }

    public static function empty(): self
    {
        return new self([], 0, 0);
    }

    /**
     * @return list<array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int}>
     */
    public function placementOf(int $missionId): array
    {
        $crates = array_filter($this->crates, static fn (PlacedCrate $crate): bool => $crate->delivery->mission()->id() === $missionId);

        return array_values(array_map(static fn (PlacedCrate $crate): array => $crate->toPlacement(), $crates));
    }

    /**
     * Rang de la caisse parmi celles de sa mission : c'est ainsi que l'interface la désigne.
     */
    public function indexInMission(PlacedCrate $target): int
    {
        $missionCrates = array_values(array_filter($this->crates, static fn (PlacedCrate $crate): bool => $crate->delivery->mission()->id() === $target->delivery->mission()->id()));

        return (int) array_search($target, $missionCrates, true);
    }

    public function crateAt(int $missionId, int $index): ?PlacedCrate
    {
        $missionCrates = array_values(array_filter($this->crates, static fn (PlacedCrate $crate): bool => $crate->delivery->mission()->id() === $missionId));

        return $missionCrates[$index] ?? null;
    }

    public function stackedCount(): int
    {
        return \count(array_filter($this->crates, static fn (PlacedCrate $crate): bool => $crate->stackedOnEarlierDelivery));
    }

    /**
     * Une caisse à charger qui prend la place d'une caisse pas encore déchargée attend que la livraison soit cochée.
     *
     * @return list<PlacedCrate>
     */
    public function visibleCrates(): array
    {
        $visible = array_filter($this->crates, fn (PlacedCrate $crate): bool => $crate->isVisibleAt($this->currentStep));
        $unloading = array_filter($visible, fn (PlacedCrate $crate): bool => $crate->isUnloadingAt($this->currentStep));

        return array_values(array_filter($visible, fn (PlacedCrate $crate): bool => !$this->waitsForUnloading($crate, $unloading)));
    }

    /**
     * @param array<PlacedCrate> $unloading
     */
    private function waitsForUnloading(PlacedCrate $crate, array $unloading): bool
    {
        if (!$crate->isLoadingAt($this->currentStep)) {
            return false;
        }

        return [] !== array_filter($unloading, static fn (PlacedCrate $leaving): bool => $leaving->overlaps($crate));
    }
}
