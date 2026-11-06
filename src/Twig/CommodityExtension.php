<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\CommodityColors;
use Twig\Attribute\AsTwigFunction;

final readonly class CommodityExtension
{
    public function __construct(private CommodityColors $commodityColors)
    {
    }

    #[AsTwigFunction('commodity_color')]
    public function color(string $commodityName): string
    {
        return $this->commodityColors->of($commodityName);
    }
}
