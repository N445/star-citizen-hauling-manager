<?php

declare(strict_types=1);

namespace App\Service\Cargo;

final class CrateSize
{
    /** @var array<int, array{width: int, length: int, height: int}> tailles standard du jeu, de la plus grande à la plus petite */
    private const array DIMENSIONS = [
        32 => ['width' => 2, 'length' => 8, 'height' => 2],
        24 => ['width' => 2, 'length' => 6, 'height' => 2],
        16 => ['width' => 2, 'length' => 4, 'height' => 2],
        8 => ['width' => 2, 'length' => 2, 'height' => 2],
        4 => ['width' => 2, 'length' => 2, 'height' => 1],
        2 => ['width' => 1, 'length' => 2, 'height' => 1],
        1 => ['width' => 1, 'length' => 1, 'height' => 1],
    ];

    /**
     * @return array{width: int, length: int, height: int}
     */
    public static function dimensions(int $size): array
    {
        return self::DIMENSIONS[$size];
    }

    /**
     * @return list<int>
     */
    public static function split(int $quantity, int $largest): array
    {
        $sizes = [];

        foreach (array_keys(array_filter(self::DIMENSIONS, static fn (array $dimensions, int $size): bool => $size <= $largest, ARRAY_FILTER_USE_BOTH)) as $size) {
            $sizes = [...$sizes, ...array_fill(0, intdiv($quantity - array_sum($sizes), $size), $size)];
        }

        return $sizes;
    }

    /**
     * @return list<int>
     */
    public static function halve(int $size): array
    {
        return match ($size) {
            32 => [16, 16],
            24 => [16, 8],
            16 => [8, 8],
            8 => [4, 4],
            4 => [2, 2],
            2 => [1, 1],
        };
    }
}
