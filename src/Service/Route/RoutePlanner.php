<?php

declare(strict_types=1);

namespace App\Service\Route;

use App\Entity\Tour;

final class RoutePlanner
{
    public function plan(Tour $tour, RouteMode $mode): Route
    {
        $tasks = TaskList::fromTour($tour);
        $tasks->assertEachMissionFits();
        $policy = $mode->policy($tasks);
        $start = $tour->startLocation();
        $stopOrder = $tour->stopOrder();
        $path = null === $stopOrder
            ? (new RouteSearch($tasks, $policy))->run($start, 0)
            : (new RouteReplay($tasks, $policy))->replay($start, 0, $stopOrder);

        return Route::fromPath($tasks, $path, $start, null !== $stopOrder);
    }
}
