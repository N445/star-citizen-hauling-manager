<?php

declare(strict_types=1);

namespace App\Service\Route;

enum RouteMode: string
{
    case FewestStops = 'fewest_stops';
    case MissionFocus = 'mission_focus';

    public function label(): string
    {
        return 'route.mode.'.$this->value.'.label';
    }

    public function description(): string
    {
        return 'route.mode.'.$this->value.'.description';
    }

    public function policy(TaskList $tasks): StopPolicy
    {
        return match ($this) {
            self::FewestStops => new FewestStopsPolicy($tasks),
            self::MissionFocus => new MissionFocusPolicy($tasks),
        };
    }
}
