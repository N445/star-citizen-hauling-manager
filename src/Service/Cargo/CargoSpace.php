<?php

declare(strict_types=1);

namespace App\Service\Cargo;

use App\Entity\DoorSide;

final readonly class CargoSpace
{
    /**
     * @param list<CargoBay> $bays
     * @param list<DoorSide> $doors
     */
    private function __construct(
        private array $bays,
        private array $doors,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $groups grille officielle de sc-cargo.space
     * @param list<DoorSide>             $doors
     */
    public static function fromGroups(array $groups, array $doors): self
    {
        $bays = array_merge(...array_map(
            static fn (array $group): array => array_map(static fn (array $grid): CargoBay => CargoBay::fromGrid($group, $grid), $group['grids']),
            $groups,
        ));

        return new self($bays, $doors);
    }

    public function largestCrate(): int
    {
        return max([1, ...array_map(static fn (CargoBay $bay): int => $bay->largestCrate(), $this->bays)]);
    }

    /**
     * @param array{x: float, y: float, z: float, width: int, height: int, length: int} $placement
     */
    public function availableSpot(array $placement): ?Spot
    {
        foreach ($this->bays as $bay) {
            $spot = $bay->availableSpot($placement['x'], $placement['y'], $placement['z'], $placement['width'], $placement['height'], $placement['length']);

            if (null !== $spot) {
                return $spot;
            }
        }

        return null;
    }

    /**
     * Marque comme occupée la place d'une caisse déjà posée, sans rien vérifier.
     *
     * @param array{x: float, y: float, z: float, width: int, height: int, length: int} $placement
     */
    public function block(array $placement): void
    {
        $spot = $this->spotInsideAGrid($placement);

        if (null !== $spot) {
            $spot->bay->occupy($spot);
        }
    }

    /**
     * La place d'une caisse posée à la main : dimensions du jeu (éventuellement tournée), dans une grille, taille admise par
     * cette grille, libre et soutenue par le sol ou par des caisses sur toute sa surface.
     *
     * @param array{x: float, y: float, z: float, width: int, height: int, length: int} $placement
     *
     * @throws PlacementException
     */
    public function spotFor(array $placement, int $size): Spot
    {
        $dimensions = CrateSize::dimensions($size);
        $straight = $placement['width'] === $dimensions['width'] && $placement['length'] === $dimensions['length'];
        $turned = $placement['width'] === $dimensions['length'] && $placement['length'] === $dimensions['width'];

        if ($placement['height'] !== $dimensions['height'] || !($straight || $turned)) {
            throw new PlacementException('cargo.edit.error.shape');
        }

        $spot = $this->spotInsideAGrid($placement) ?? throw new PlacementException('cargo.edit.error.outside');

        return match (true) {
            !$spot->bay->accepts($size) => throw new PlacementException('cargo.edit.error.size'),
            !$spot->bay->isFree($spot) => throw new PlacementException('cargo.edit.error.overlap'),
            !$spot->bay->isSupported($spot) => throw new PlacementException('cargo.edit.error.unsupported'),
            default => $spot,
        };
    }

    /**
     * @param array{x: float, y: float, z: float, width: int, height: int, length: int} $placement
     */
    public function isSupported(array $placement): bool
    {
        $spot = $this->spotInsideAGrid($placement);

        return null === $spot || $spot->bay->isSupported($spot);
    }

    /**
     * @param array{x: float, y: float, z: float, width: int, height: int, length: int} $placement
     */
    private function spotInsideAGrid(array $placement): ?Spot
    {
        foreach ($this->bays as $bay) {
            $spot = $bay->spotAt($placement['x'], $placement['y'], $placement['z'], $placement['width'], $placement['height'], $placement['length']);

            if (null !== $spot) {
                return $spot;
            }
        }

        return null;
    }

    /**
     * Priorités : ne pas être posée sur une caisse livrée avant, ne pas lui couper l'accès depuis toutes les portes,
     * rester près des caisses de même destination (sinon viser la profondeur : 0 = porte, 1 = fond), être le plus bas.
     *
     * @param list<Spot> $deliveredEarlier
     * @param list<Spot> $sameDestination
     */
    public function bestSpotFor(int $size, float $targetDepth, array $deliveredEarlier, array $sameDestination): ?Spot
    {
        return $this->bestPlacement($size, $targetDepth, $deliveredEarlier, $sameDestination)['spot'] ?? null;
    }

    /**
     * @param list<Spot> $deliveredEarlier
     * @param list<Spot> $sameDestination
     */
    public function stacksOnEarlierDelivery(int $size, float $targetDepth, array $deliveredEarlier, array $sameDestination): bool
    {
        return ($this->bestPlacement($size, $targetDepth, $deliveredEarlier, $sameDestination)['stacked'] ?? 0) > 0;
    }

    /**
     * @param list<Spot> $deliveredEarlier
     * @param list<Spot> $sameDestination
     *
     * @return array{spot: Spot, stacked: int}|null
     */
    private function bestPlacement(int $size, float $targetDepth, array $deliveredEarlier, array $sameDestination): ?array
    {
        $spots = array_merge(...array_map(static fn (CargoBay $bay): array => $bay->spotsFor($size), $this->bays));
        $scored = array_map(fn (Spot $spot): array => [...$this->score($spot, $deliveredEarlier, $sameDestination, $targetDepth), $spot], $spots);
        usort($scored, static fn (array $first, array $second): int => \array_slice($first, 0, 4) <=> \array_slice($second, 0, 4));

        return [] === $scored ? null : ['spot' => $scored[0][4], 'stacked' => $scored[0][0]];
    }

    /**
     * @param list<Spot> $deliveredEarlier
     * @param list<Spot> $sameDestination
     *
     * @return array{int, int, float, float}
     */
    private function score(Spot $spot, array $deliveredEarlier, array $sameDestination, float $targetDepth): array
    {
        return [
            \count(array_filter($deliveredEarlier, fn (Spot $earlier): bool => $this->isAbove($spot, $earlier))),
            \count(array_filter($deliveredEarlier, fn (Spot $earlier): bool => $this->cutsOff($spot, $earlier))),
            [] === $sameDestination ? abs($this->depthOf($spot) - $targetDepth) : $this->distanceToGroup($spot, $sameDestination),
            $spot->y(),
        ];
    }

    /**
     * @param list<Spot> $deliveredEarlier
     */
    public function isStackedOnAny(Spot $spot, array $deliveredEarlier): bool
    {
        return [] !== array_filter($deliveredEarlier, fn (Spot $earlier): bool => $this->isAbove($spot, $earlier));
    }

    private function isAbove(Spot $spot, Spot $earlier): bool
    {
        return $spot->y() >= $earlier->y() + $earlier->height && $spot->overlapsAlong($earlier, 'x') && $spot->overlapsAlong($earlier, 'z');
    }

    private function cutsOff(Spot $spot, Spot $earlier): bool
    {
        return [] === array_filter($this->doors, fn (DoorSide $door): bool => !$this->isBetweenDoorAnd($door, $spot, $earlier));
    }

    private function isBetweenDoorAnd(DoorSide $door, Spot $spot, Spot $earlier): bool
    {
        $axis = $door->axis();
        [$crossAxis, $otherCrossAxis] = array_values(array_diff(['x', 'y', 'z'], [$axis]));
        [$start, $end] = $spot->span($axis);
        [$earlierStart, $earlierEnd] = $earlier->span($axis);
        $towardsDoor = $door->isAtMaximum() ? $start >= $earlierEnd : $end <= $earlierStart;

        return $towardsDoor && $spot->overlapsAlong($earlier, $crossAxis) && $spot->overlapsAlong($earlier, $otherCrossAxis);
    }

    /**
     * @param list<Spot> $group
     */
    private function distanceToGroup(Spot $spot, array $group): float
    {
        return min(array_map(static fn (Spot $member): float => array_sum(array_map(
            static fn (string $axis): float => abs(array_sum($spot->span($axis)) - array_sum($member->span($axis))) / 2,
            ['x', 'y', 'z'],
        )), $group));
    }

    private function depthOf(Spot $spot): float
    {
        return min(array_map(fn (DoorSide $door): float => $this->depthFrom($door, $spot), $this->doors));
    }

    private function depthFrom(DoorSide $door, Spot $spot): float
    {
        $axis = $door->axis();
        [$start, $end] = $spot->span($axis);
        $extent = $this->bound($axis, true) - $this->bound($axis, false) - ($end - $start);
        $distance = $door->isAtMaximum() ? $this->bound($axis, true) - $end : $start - $this->bound($axis, false);

        return $extent > 0 ? $distance / $extent : 0.0;
    }

    private function bound(string $axis, bool $maximum): float
    {
        $size = ['x' => 'width', 'y' => 'height', 'z' => 'length'][$axis];
        $edges = array_map(static fn (CargoBay $bay): float => $bay->{$axis} + ($maximum ? $bay->{$size} : 0), $this->bays);

        return $maximum ? max($edges) : min($edges);
    }
}
