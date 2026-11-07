<?php

declare(strict_types=1);

namespace App\Service\Cargo;

use App\Entity\Tour;
use App\Service\Route\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Placement manuel des caisses : valide le déplacement par rapport à ce que le joueur voit à l'étape courante, puis
 * fige l'emplacement de toute la mission (marqué « manual ») pour que le calcul de la soute le respecte.
 */
final readonly class CargoEditor
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array{x: float, y: float, z: float, width: int, length: int} $target
     *
     * @throws PlacementException
     * @throws \InvalidArgumentException
     */
    public function move(Tour $tour, Route $route, int $missionId, int $index, array $target): void
    {
        $plan = (new CargoPlanner())->plan($tour, $route);
        $crate = $this->visibleCrate($plan, $missionId, $index);
        $this->assertNotPlaced($crate);
        $moved = [...$crate->toPlacement(), ...$target];

        PlacementRules::assertLayout($this->groups($tour), $this->placementsExcept($plan, [$crate]), [$moved]);
        $this->freeze($tour, $plan, [[$crate, $moved]], true);
    }

    /**
     * « Cette caisse-là est bien posée » : l'emplacement de la mission est figé tel quel, la caisse n'est plus déplaçable
     * tant qu'on ne la décoche pas.
     *
     * @throws \InvalidArgumentException
     */
    public function markPlaced(Tour $tour, Route $route, int $missionId, int $index, bool $placed): void
    {
        $plan = (new CargoPlanner())->plan($tour, $route);
        $crate = $this->visibleCrate($plan, $missionId, $index);
        $entry = $crate->toPlacement();
        unset($entry['placed']);

        $this->freeze($tour, $plan, [[$crate, $placed ? [...$entry, 'placed' => true] : $entry]], false);
    }

    /**
     * @throws PlacementException
     * @throws \InvalidArgumentException
     */
    public function swap(Tour $tour, Route $route, int $missionId, int $index, int $otherMissionId, int $otherIndex): void
    {
        $plan = (new CargoPlanner())->plan($tour, $route);
        $first = $this->visibleCrate($plan, $missionId, $index);
        $second = $this->visibleCrate($plan, $otherMissionId, $otherIndex);

        $this->assertNotPlaced($first);
        $this->assertNotPlaced($second);

        if ($first === $second || $first->size !== $second->size) {
            throw new PlacementException('cargo.edit.error.swap');
        }

        $spots = ['x', 'y', 'z', 'width', 'height', 'length'];
        $firstMoved = [...$first->toPlacement(), ...array_intersect_key($second->toPlacement(), array_flip($spots))];
        $secondMoved = [...$second->toPlacement(), ...array_intersect_key($first->toPlacement(), array_flip($spots))];

        PlacementRules::assertLayout($this->groups($tour), $this->placementsExcept($plan, [$first, $second]), [$firstMoved, $secondMoved]);
        $this->freeze($tour, $plan, [[$first, $firstMoved], [$second, $secondMoved]], true);
    }

    /**
     * Efface les emplacements manuels (d'une mission, ou de toute la tournée) et revient au placement automatique.
     * Une mission déjà ramassée garde la proposition automatique figée, sinon ses caisses bougeraient au prochain calcul.
     */
    public function reset(Tour $tour, Route $route, ?int $missionId = null): void
    {
        $missions = array_filter($tour->missions()->getValues(), static fn ($mission): bool => $mission->hasManualCargo() && (null === $missionId || $mission->id() === $missionId));

        foreach ($missions as $mission) {
            $mission->clearCargo();
        }

        $plan = (new CargoPlanner())->plan($tour, $route);

        foreach (array_filter($missions, static fn ($mission): bool => $mission->hasPickedUpAnything()) as $mission) {
            $placement = $plan->placementOf($mission->id());

            if ([] !== $placement) {
                $mission->freezeCargo($placement);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @param list<array{PlacedCrate, array<string, mixed>}> $moves
     */
    private function freeze(Tour $tour, CargoPlan $plan, array $moves, bool $manual): void
    {
        $placements = [];

        foreach ($moves as [$crate, $moved]) {
            $missionId = $crate->delivery->mission()->id();
            $placements[$missionId] ??= $plan->placementOf($missionId);
            $placements[$missionId][$plan->indexInMission($crate)] = $moved;
        }

        foreach ($placements as $missionId => $placement) {
            $tour->mission($missionId)->freezeCargo(array_map(static fn (array $crate): array => [
                'delivery' => $crate['delivery'],
                ...(isset($crate['pickup']) ? ['pickup' => $crate['pickup']] : []),
                'size' => $crate['size'],
                'x' => (float) $crate['x'],
                'y' => (float) $crate['y'],
                'z' => (float) $crate['z'],
                'width' => $crate['width'],
                'height' => $crate['height'],
                'length' => $crate['length'],
                ...($manual || ($crate['manual'] ?? false) ? ['manual' => true] : []),
                ...($crate['placed'] ?? false ? ['placed' => true] : []),
            ], $placement));
        }

        $this->entityManager->flush();
    }

    private function assertNotPlaced(PlacedCrate $crate): void
    {
        if ($crate->placed) {
            throw new PlacementException('cargo.edit.error.placed');
        }
    }

    private function visibleCrate(CargoPlan $plan, int $missionId, int $index): PlacedCrate
    {
        $crate = $plan->crateAt($missionId, $index);

        if (null === $crate || !\in_array($crate, $plan->visibleCrates(), true)) {
            throw new \InvalidArgumentException('Cette caisse n\'est pas dans la soute à cette étape.');
        }

        return $crate;
    }

    /**
     * @param list<PlacedCrate> $excluded
     *
     * @return list<array{delivery: int, size: int, x: float, y: float, z: float, width: int, height: int, length: int}>
     */
    private function placementsExcept(CargoPlan $plan, array $excluded): array
    {
        $others = array_filter($plan->visibleCrates(), static fn (PlacedCrate $crate): bool => !\in_array($crate, $excluded, true));

        return array_values(array_map(static fn (PlacedCrate $crate): array => $crate->toPlacement(), $others));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function groups(Tour $tour): array
    {
        return $tour->ship()?->cargoGroups() ?? [];
    }
}
