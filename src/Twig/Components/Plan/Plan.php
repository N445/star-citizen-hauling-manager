<?php

declare(strict_types=1);

namespace App\Twig\Components\Plan;

use App\Domain\Placement;
use App\Domain\Route;

final readonly class Plan
{
    /**
     * @param array<string, array{label: string, color: string}>                                                $missions indexed by mission id
     * @param array<string, array{missionId: string, resource: string, pickup: string, dropoff: string}>        $lots     indexed by lot id
     */
    public function __construct(
        public Route $route,
        public Placement $placement,
        public array $missions,
        public array $lots,
    ) {
    }
}
