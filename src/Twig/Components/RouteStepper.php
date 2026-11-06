<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Domain\DoorSide;
use App\Domain\PlacedCrate;
use App\Domain\TravelTier;
use App\Twig\Catalog;
use App\Twig\Components\Plan\Plan;
use App\Twig\Components\Plan\PlanFactory;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class RouteStepper
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** @var array<string, mixed> empty until the planner emits a calculated plan */
    #[LiveProp]
    public array $input = [];

    #[LiveProp]
    public int $step = 0;

    #[LiveProp]
    public int $layer = 0;

    /** Last refusal of a crate move, shown under the grid. */
    #[LiveProp]
    public ?string $notice = null;

    private ?Plan $plan = null;

    public function __construct(private readonly PlanFactory $factory)
    {
    }

    /** @param array<string, mixed> $input */
    #[LiveListener('planCalculated')]
    public function onPlanCalculated(#[LiveArg] array $input): void
    {
        $this->input = $input;
        $this->step = 0;
        $this->layer = 0;
        $this->plan = null;
        $this->notice = null;
    }

    #[LiveListener('planReset')]
    public function onPlanReset(): void
    {
        $this->input = [];
        $this->step = 0;
        $this->layer = 0;
        $this->plan = null;
    }

    #[LiveAction]
    public function moveCrate(#[LiveArg] string $crate, #[LiveArg] int $x, #[LiveArg] int $y, #[LiveArg] int $z, #[LiveArg] int $orientation): void
    {
        $reason = $this->getPlan()->placement->canPlace($crate, $x, $y, $z, $orientation);
        if (null !== $reason) {
            $this->notice = $reason;

            return;
        }

        $this->notice = null;
        $this->setPins([...$this->input['pins'] ?? [], $crate => ['x' => $x, 'y' => $y, 'z' => $z, 'o' => $orientation]]);
    }

    #[LiveAction]
    public function unpin(#[LiveArg] string $crate): void
    {
        $pins = $this->input['pins'] ?? [];
        unset($pins[$crate]);
        $this->notice = null;
        $this->setPins($pins);
    }

    #[LiveAction]
    public function clearPins(): void
    {
        $this->notice = null;
        $this->setPins([]);
    }

    #[LiveAction]
    public function changeDoor(#[LiveArg] string $door): void
    {
        $side = DoorSide::tryFrom($door) ?? DoorSide::Rear;
        $this->input['door'] = $side->value;
        $this->plan = null;
        $this->emit('doorChanged', ['ship' => $this->input['ship'], 'door' => $side->value]);
    }

    public function currentDoor(): string
    {
        return (string) ($this->input['door'] ?? DoorSide::Rear->value);
    }

    /** @return array<string, array{x: int, y: int, z: int, o: int}> */
    public function getPins(): array
    {
        return $this->input['pins'] ?? [];
    }

    public function hasPlan(): bool
    {
        return [] !== $this->input;
    }

    public function getPlan(): Plan
    {
        return $this->plan ??= $this->factory->build($this->input);
    }

    public function getStopCount(): int
    {
        return \count($this->getPlan()->route->stops);
    }

    public function getCurrentStep(): int
    {
        return max(0, min($this->step, $this->getStopCount() - 1));
    }

    public function getNextTravel(): ?string
    {
        return match ($this->getPlan()->route->stops[$this->getCurrentStep()]->tierToNext) {
            TravelTier::Local => 'Local',
            TravelTier::Planetary => 'Planétaire',
            TravelTier::Stellar => 'Stellaire',
            TravelTier::Interstellar => 'Interstellaire',
            null => null,
        };
    }

    #[LiveAction]
    public function next(): void
    {
        $this->step = min($this->step + 1, max(0, $this->getStopCount() - 1));
    }

    #[LiveAction]
    public function previous(): void
    {
        $this->step = max(0, $this->step - 1);
    }

    #[LiveAction]
    public function goTo(#[LiveArg] int $index): void
    {
        $this->step = max(0, min($index, $this->getStopCount() - 1));
    }

    #[LiveAction]
    public function selectLayer(#[LiveArg] int $layer): void
    {
        $this->layer = max(0, min($layer, $this->getPlan()->placement->ship->grid->height - 1));
    }

    /**
     * Actions of the current stop, grouped by mission then by lot.
     *
     * @return list<array{label: string, color: string, type: string, pickups: list<array<string, mixed>>, drops: list<array<string, mixed>>}>
     */
    public function getMissionActions(): array
    {
        $plan = $this->getPlan();
        $step = $this->getCurrentStep();
        $groups = [];

        $byKind = ['pickups' => $plan->placement->toPickupAt($step), 'drops' => $plan->placement->toDropAt($step)];
        foreach ($byKind as $kind => $placed) {
            foreach ($placed as $p) {
                $crate = $p->crate;
                $mission = $plan->missions[$crate->missionId];
                $lot = $plan->lots[$crate->lotId];
                $groups[$crate->missionId] ??= ['label' => $mission['label'], 'color' => $mission['color'], 'type' => Catalog::typeLabel($mission['type'] ?? 'direct'), 'pickups' => [], 'drops' => []];
                $groups[$crate->missionId][$kind][$crate->lotId] ??= [
                    'resource' => $lot['resource'],
                    'target' => 'pickups' === $kind ? $lot['dropoff'] : $lot['pickup'],
                    'sizes' => [],
                    'scu' => 0,
                ];
                $groups[$crate->missionId][$kind][$crate->lotId]['sizes'][] = $crate->sizeScu;
                $groups[$crate->missionId][$kind][$crate->lotId]['scu'] += $crate->sizeScu;
            }
        }

        ksort($groups);

        return array_values(array_map(static fn (array $g): array => [
            ...$g,
            'pickups' => array_values($g['pickups']),
            'drops' => array_values($g['drops']),
        ], $groups));
    }

    /** @return list<PlacedCrate> */
    public function getGridCrates(): array
    {
        return $this->getPlan()->placement->gridAt($this->getCurrentStep());
    }

    /** @return list<string> */
    public function getPickupIds(): array
    {
        return array_map(static fn (PlacedCrate $p): string => $p->crate->id, $this->getPlan()->placement->toPickupAt($this->getCurrentStep()));
    }

    /** @return list<string> */
    public function getDropIds(): array
    {
        return array_map(static fn (PlacedCrate $p): string => $p->crate->id, $this->getPlan()->placement->toDropAt($this->getCurrentStep()));
    }

    public function getLoad(): int
    {
        return array_sum(array_map(static fn (PlacedCrate $p): int => $p->crate->sizeScu, $this->getGridCrates()));
    }

    /** @param array<string, array{x: int, y: int, z: int, o: int}> $pins */
    private function setPins(array $pins): void
    {
        $this->input['pins'] = $pins;
        $this->plan = null;
        $this->emit('pinsChanged', ['pins' => $pins]);
    }
}
