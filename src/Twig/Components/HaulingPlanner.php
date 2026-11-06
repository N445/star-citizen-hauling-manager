<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Domain\ShipCatalog;
use App\Twig\Catalog;
use App\Twig\Components\Plan\PlanFactory;
use App\Twig\Components\Plan\PlannerState;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PreReRender;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class HaulingPlanner
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** @var list<array{uid: string, label: string, reward: int, lots: list<array{uid: string, resource: string, scu: int, pickup: string, dropoff: string}>}> */
    #[LiveProp(writable: true)]
    public array $missions = [];

    #[LiveProp(writable: true)]
    public string $ship = ShipCatalog::DEFAULT;

    #[LiveProp(writable: true)]
    public string $start = PlannerState::DEFAULT_START;

    /** @var array<string, string> door side chosen per ship slug (rear by default) */
    #[LiveProp(writable: true)]
    public array $doors = [];

    /** @var array<string, array{x: int, y: int, z: int, o: int}> crate id => position fixed by the user */
    #[LiveProp(writable: true)]
    public array $pins = [];

    #[LiveProp]
    public ?string $error = null;

    /** Whether a plan was computed (it is rendered by RouteStepper, never by this component). */
    #[LiveProp(writable: true)]
    public bool $calculated = false;

    public function __construct(private readonly PlanFactory $factory)
    {
    }

    public function mount(): void
    {
        $this->missions = [PlannerState::emptyMission()];
    }

    /** Carries compatible values over when the user changed a mission type. */
    #[PreReRender]
    public function adaptMissionTypes(): void
    {
        $this->missions = array_map(PlannerState::adaptType(...), $this->missions);
    }

    public function typeLabel(string $type): string
    {
        return Catalog::typeLabel($type);
    }

    public function shipLabel(): string
    {
        return Catalog::shipLabel($this->ship);
    }

    public function locationName(string $id): string
    {
        return Catalog::locationName($id);
    }

    #[LiveAction]
    public function addMission(): void
    {
        if (\count($this->missions) < PlannerState::MAX_MISSIONS) {
            $this->missions[] = PlannerState::emptyMission();
        }
    }

    #[LiveAction]
    public function removeMission(#[LiveArg] int $index): void
    {
        unset($this->missions[$index]);
        $this->missions = array_values($this->missions);
    }

    #[LiveAction]
    public function addLot(#[LiveArg] int $mission): void
    {
        if (isset($this->missions[$mission]) && \count($this->missions[$mission]['lots']) < PlannerState::MAX_LOTS) {
            $lot = PlannerState::emptyLot();
            $lot['pickup'] = $this->missions[$mission]['pickup'] ?? $lot['pickup'];
            $lot['dropoff'] = ($this->missions[$mission]['dropoff'] ?? '') ?: $lot['dropoff'];
            $this->missions[$mission]['lots'][] = $lot;
        }
    }

    #[LiveAction]
    public function removeLot(#[LiveArg] int $mission, #[LiveArg] int $lot): void
    {
        if (!isset($this->missions[$mission])) {
            return;
        }
        unset($this->missions[$mission]['lots'][$lot]);
        $this->missions[$mission]['lots'] = array_values($this->missions[$mission]['lots']);
    }

    #[LiveAction]
    public function calculate(): void
    {
        $this->adaptMissionTypes();
        $state = PlannerState::normalize(['ship' => $this->ship, 'start' => $this->start, 'missions' => $this->missions, 'doors' => $this->doors, 'pins' => $this->pins]);
        $input = PlannerState::planInput($state);

        try {
            $this->factory->build($input);
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->error = null;
        $this->calculated = true;
        $this->emit('planCalculated', ['input' => $input]);
    }

    /** Restores a state saved in the browser; anything invalid falls back to defaults. */
    #[LiveAction]
    public function restore(#[LiveArg] string $state): void
    {
        $decoded = json_decode($state, true);
        $clean = PlannerState::normalize($decoded);
        $this->ship = $clean['ship'];
        $this->start = $clean['start'];
        $this->missions = $clean['missions'];
        $this->doors = $clean['doors'];
        $this->pins = $clean['pins'];
        $this->error = null;

        if ($clean['calculated']) {
            $this->calculate();
        }
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->ship = ShipCatalog::DEFAULT;
        $this->start = PlannerState::DEFAULT_START;
        $this->missions = [PlannerState::emptyMission()];
        $this->doors = [];
        $this->pins = [];
        $this->error = null;
        $this->calculated = false;
        $this->emit('planReset');
    }

    /** @param array<string, mixed> $pins */
    #[LiveListener('pinsChanged')]
    public function onPinsChanged(#[LiveArg] array $pins): void
    {
        $this->pins = PlannerState::normalize(['pins' => $pins])['pins'];
    }

    #[LiveListener('doorChanged')]
    public function onDoorChanged(#[LiveArg] string $ship, #[LiveArg] string $door): void
    {
        $this->doors = PlannerState::normalize(['doors' => [...$this->doors, $ship => $door]])['doors'];
    }
}
