<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class CargoPlacer
{
    /**
     * @param list<Pin> $pins user-fixed positions; invalid ones are ignored and reported by the placement
     */
    public function place(Route $route, Ship $ship, array $pins = []): Placement
    {
        $grid = $ship->grid;
        $items = $this->lifespans($route);
        $occupancy = new CargoOccupancy($grid);
        $placed = [];
        $ignored = [];

        $byId = [];
        foreach ($items as $item) {
            $byId[$item['crate']->id] = $item;
        }
        $pinned = [];
        foreach ($pins as $pin) {
            $item = $byId[$pin->crateId] ?? null;
            if (null === $item || isset($pinned[$pin->crateId])
                || null !== $occupancy->reason($item['crate'], $item['pickup'], $item['drop'], $pin->orientation, $pin->x, $pin->y, $pin->z)) {
                $ignored[] = $pin->crateId;
                continue;
            }
            [$w, $l, $h] = $item['crate']->orientations()[$pin->orientation];
            $block = $occupancy->blockIndex($pin->x, $pin->y, $pin->z, $w, $l, $h);
            $crate = new PlacedCrate($item['crate'], $pin->x, $pin->y, $pin->z, $w, $l, $h, $item['pickup'], $item['drop'], $block ?? 0, $pin->orientation, $item['kept']);
            $occupancy->add($crate);
            $placed[] = $crate;
            $pinned[$pin->crateId] = true;
        }

        $items = array_values(array_filter($items, static fn (array $i): bool => !isset($pinned[$i['crate']->id])));

        // Loaded first then dropped last goes deepest; same drop: crates of a mission stay together.
        usort($items, static fn (array $a, array $b): int => [$a['pickup'], $b['drop'], $a['crate']->missionId, $a['crate']->lotId, $a['crate']->id]
            <=> [$b['pickup'], $a['drop'], $b['crate']->missionId, $b['crate']->lotId, $b['crate']->id]);

        // Best achievable depth per block, to skip blocks that cannot beat the current best.
        $blockDepth = [];
        foreach ($grid->blocks as $index => $block) {
            $blockDepth[$index] = $this->depth($grid, $block->x, $block->y, $block->width, $block->length, true);
        }

        foreach ($items as ['crate' => $crate, 'pickup' => $pickup, 'drop' => $drop, 'kept' => $kept]) {
            $spot = $this->findSpot($grid, $crate, $pickup, $drop, $occupancy, $blockDepth)
                ?? throw new \DomainException(sprintf('Aucun emplacement pour une caisse de %d SCU dans %s', $crate->sizeScu, $ship->name));

            [, $b, $x, $y, $z, $o] = $spot;
            [$w, $l, $h] = $crate->orientations()[$o];
            $placedCrate = new PlacedCrate($crate, $x, $y, $z, $w, $l, $h, $pickup, $drop, $b, $o, $kept);
            $occupancy->add($placedCrate);
            $placed[] = $placedCrate;
        }

        return new Placement($route, $ship, $placed, $ignored);
    }

    /**
     * A crate picked up but never dropped is kept aboard: its stay ends on the last stop.
     *
     * @return list<array{crate: Crate, pickup: int, drop: int, kept: bool}>
     */
    private function lifespans(Route $route): array
    {
        $pickups = [];
        $drops = [];
        foreach ($route->stops as $index => $stop) {
            foreach ($stop->pickups as $crate) {
                $pickups[$crate->id] = ['crate' => $crate, 'pickup' => $index];
            }
            foreach ($stop->drops as $crate) {
                $drops[$crate->id] = $index;
            }
        }

        $items = [];
        foreach ($pickups as $id => $item) {
            $items[] = [...$item, 'drop' => $drops[$id] ?? count($route->stops) - 1, 'kept' => !isset($drops[$id])];
        }

        return $items;
    }

    /**
     * @param array<int, int> $blockDepth
     *
     * @return array{array{int, int, int, int}, int, int, int, int, int}|null [score, block, x, y, z, orientation]
     */
    private function findSpot(CargoGrid $grid, Crate $crate, int $pickup, int $drop, CargoOccupancy $occupancy, array $blockDepth): ?array
    {
        $best = null;
        foreach ($grid->blocks as $b => $block) {
            if (!$block->accepts($crate)) {
                continue;
            }
            if (null !== $best && $blockDepth[$b] < -$best[0][0]) {
                continue;
            }
            foreach ($crate->orientations() as $o => [$w, $l, $h]) {
                for ($z = $block->z; $z + $h <= $block->z + $block->height; ++$z) {
                    for ($y = $block->y; $y + $l <= $block->y + $block->length; ++$y) {
                        for ($x = $block->x; $x + $w <= $block->x + $block->width; ++$x) {
                            $score = [-$this->depth($grid, $x, $y, $w, $l), $z, $x, $y];
                            if (null !== $best && $score >= $best[0]) {
                                continue;
                            }
                            if ($occupancy->isFree($b, $x, $y, $z, $w, $l, $h, $pickup, $drop)) {
                                $best = [$score, $b, $x, $y, $z, $o];
                            }
                        }
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Distance to the door of the crate cell nearest to it (or, with $maxOnly, of the block's deepest cell).
     */
    private function depth(CargoGrid $grid, int $x, int $y, int $w, int $l, bool $maxOnly = false): int
    {
        $corners = [[$x, $y], [$x + $w - 1, $y], [$x, $y + $l - 1], [$x + $w - 1, $y + $l - 1]];
        $distances = array_map(static fn (array $c): int => $grid->doorDistance($c[0], $c[1]), $corners);

        return $maxOnly ? max($distances) : min($distances);
    }
}
