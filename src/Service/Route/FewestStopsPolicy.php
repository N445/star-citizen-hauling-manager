<?php

declare(strict_types=1);

namespace App\Service\Route;

final readonly class FewestStopsPolicy implements StopPolicy
{
    public function __construct(private TaskList $tasks)
    {
    }

    /**
     * On ramasse tout ce qui rentre ; quand la capacité force un choix, une option par mission à charger en priorité.
     */
    public function stopAt(string $location, int $done): array
    {
        $done = $this->tasks->deliverAt($location, $done);
        $candidates = $this->tasks->missionsToPickUpAt($location, $done);
        $prioritised = array_map(
            fn (int $first): int => $this->pickUpInOrder($location, $done, [$first, ...array_diff($candidates, [$first])]),
            $candidates,
        );

        return array_values(array_unique([$this->pickUpInOrder($location, $done, $candidates), ...$prioritised]));
    }

    /**
     * @param list<int> $missionIndexes
     */
    private function pickUpInOrder(string $location, int $done, array $missionIndexes): int
    {
        foreach ($missionIndexes as $missionIndex) {
            $done = $this->tasks->pickUpIfItFits($missionIndex, $location, $done);
        }

        return $this->tasks->deliverAt($location, $done);
    }
}
