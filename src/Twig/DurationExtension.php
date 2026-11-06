<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class DurationExtension
{
    #[AsTwigFilter('duration')]
    public static function format(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%d s', $seconds);
        }

        if ($seconds < 3600) {
            return sprintf('%d min', intdiv($seconds, 60));
        }

        return sprintf('%d h %02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
