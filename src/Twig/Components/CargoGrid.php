<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Domain\DoorSide;
use App\Domain\PlacedCrate;
use App\Domain\Ship;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Draws the cargo blocks of a ship (not a full rectangle): one element per block and per crate,
 * empty cells are a CSS background so even a 4608 SCU ship stays a few hundred nodes.
 */
#[AsTwigComponent]
final class CargoGrid
{
    private const MAX_CELL = 34;
    private const MIN_CELL = 9;
    private const TARGET_WIDTH = 760;

    public Ship $ship;

    /** @var list<PlacedCrate> crates aboard at the current stop (absolute coordinates) */
    public array $crates = [];

    /** @var list<string> */
    public array $pickupIds = [];

    /** @var list<string> */
    public array $dropIds = [];

    /** @var array<string, array{label: string, color: string}> */
    public array $missions = [];

    public int $layer = 0;

    /** @var array<string, mixed> user-pinned crates, keyed by crate id */
    public array $pins = [];

    public function getCellSize(): int
    {
        $grid = $this->ship->grid;

        return max(self::MIN_CELL, min(self::MAX_CELL, intdiv(self::TARGET_WIDTH, max($grid->width, 1))));
    }

    /**
     * Blocks crossing the selected layer.
     *
     * @return list<array{col: int, row: int, cols: int, rows: int, max: ?int, index: int}>
     */
    public function getBlocks(): array
    {
        $blocks = [];
        foreach ($this->ship->grid->blocks as $index => $b) {
            if ($this->layer < $b->z || $this->layer >= $b->z + $b->height) {
                continue;
            }
            $blocks[] = ['col' => $b->x + 1, 'row' => $b->y + 1, 'cols' => $b->width, 'rows' => $b->length, 'max' => $b->maxSize, 'index' => $index];
        }

        return $blocks;
    }

    /**
     * Crates crossing the selected layer, with their CSS grid area and highlight state.
     *
     * @return list<array{id: string, size: int, mission: string, color: string, state: string, col: int, row: int, cols: int, rows: int, x: int, y: int, z: int, o: int, height: int, pinned: bool}>
     */
    public function getCells(): array
    {
        $pickups = array_flip($this->pickupIds);
        $drops = array_flip($this->dropIds);
        $highlight = [] !== $pickups || [] !== $drops;
        $cells = [];
        foreach ($this->crates as $p) {
            if ($this->layer < $p->z || $this->layer >= $p->z + $p->height) {
                continue;
            }
            $id = $p->crate->id;
            $state = match (true) {
                isset($pickups[$id]) => 'pickup',
                isset($drops[$id]) => 'drop',
                default => $highlight ? 'dim' : 'idle',
            };
            $mission = $this->missions[$p->crate->missionId] ?? ['label' => $p->crate->missionId, 'color' => '#888'];
            $cells[] = [
                'id' => $id,
                'size' => $p->crate->sizeScu,
                'mission' => $mission['label'],
                'color' => $mission['color'],
                'state' => $state,
                'col' => $p->x + 1,
                'row' => $p->y + 1,
                'cols' => $p->width,
                'rows' => $p->length,
                'x' => $p->x,
                'y' => $p->y,
                'z' => $p->z,
                'o' => $p->orientation,
                'height' => $p->height,
                'pinned' => isset($this->pins[$id]),
            ];
        }

        return $cells;
    }

    /** @return list<int> */
    public function getLayers(): array
    {
        return range(0, $this->ship->grid->height - 1);
    }

    public function getDoorSide(): string
    {
        return match ($this->ship->grid->door) {
            DoorSide::Rear => 'bottom',
            DoorSide::Front => 'top',
            DoorSide::Left => 'left',
            DoorSide::Right => 'right',
        };
    }
}
