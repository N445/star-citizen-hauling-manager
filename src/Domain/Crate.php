<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Crate
{
    public const SIZES = [1, 2, 4, 8, 16, 24, 32];

    private const FOOTPRINTS = [
        1 => [1, 1, 1],
        2 => [2, 1, 1],
        4 => [2, 2, 1],
        8 => [2, 2, 2],
        16 => [4, 2, 2],
        24 => [6, 2, 2],
        32 => [8, 2, 2],
    ];

    public function __construct(
        public string $id,
        public string $lotId,
        public string $missionId,
        public int $sizeScu,
    ) {
        if (!in_array($sizeScu, self::SIZES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid crate size %d SCU.', $sizeScu));
        }
    }

    /**
     * Greedy cut of a lot into crates (largest sizes first, none above $maxSize).
     *
     * @return list<Crate>
     */
    public static function split(CargoLot $lot, int $maxSize = 32): array
    {
        $crates = [];
        $remaining = $lot->scu;
        foreach (array_reverse(self::SIZES) as $size) {
            if ($size > $maxSize) {
                continue;
            }
            while ($remaining >= $size) {
                $crates[] = new self(sprintf('%s-%d', $lot->id, count($crates) + 1), $lot->id, $lot->missionId, $size);
                $remaining -= $size;
            }
        }

        return $crates;
    }

    /**
     * Unique orientations of the crate as [x, y, z] extents in cells.
     *
     * @return list<array{int, int, int}>
     */
    public function orientations(): array
    {
        [$a, $b, $c] = self::FOOTPRINTS[$this->sizeScu];
        $all = [[$a, $b, $c], [$a, $c, $b], [$b, $a, $c], [$b, $c, $a], [$c, $a, $b], [$c, $b, $a]];

        return array_values(array_unique($all, SORT_REGULAR));
    }
}
