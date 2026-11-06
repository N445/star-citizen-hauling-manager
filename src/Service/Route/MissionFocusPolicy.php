<?php

declare(strict_types=1);

namespace App\Service\Route;

final readonly class MissionFocusPolicy implements StopPolicy
{
    public function __construct(private TaskList $tasks)
    {
    }

    public function stopAt(string $location, int $done): array
    {
        $done = $this->tasks->deliverAt($location, $done);
        $inProgress = $this->tasks->missionInProgress($done);

        if (null !== $inProgress) {
            return [$this->pickUp($inProgress, $location, $done)];
        }

        $starts = array_map(
            fn (int $missionIndex): int => $this->pickUp($missionIndex, $location, $done),
            $this->tasks->missionsToPickUpAt($location, $done),
        );

        return [$done, ...$starts];
    }

    private function pickUp(int $missionIndex, string $location, int $done): int
    {
        return $this->tasks->deliverAt($location, $this->tasks->pickUpIfItFits($missionIndex, $location, $done));
    }
}
