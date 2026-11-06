<?php

declare(strict_types=1);

namespace App\Service\Route;

use App\Entity\Delivery;
use App\Entity\Mission;

final readonly class Stop
{
    /**
     * @param list<Delivery> $deliveries
     * @param list<Mission>  $pickups
     */
    public function __construct(
        public string $location,
        public array $deliveries,
        public array $pickups,
        public ?int $load,
    ) {
    }

    /**
     * @param list<Task> $tasks
     */
    public static function fromTasks(TaskList $taskList, string $location, array $tasks, ?int $load): self
    {
        $deliveries = array_values(array_filter(array_map(static fn (Task $task): ?Delivery => $task->delivery, $tasks)));
        $pickups = array_map(static fn (Task $task): Mission => $taskList->mission($task->missionIndex), array_filter($tasks, static fn (Task $task): bool => $task->isPickup()));

        return new self($location, $deliveries, array_values($pickups), $load);
    }

    public function isEmpty(): bool
    {
        return [] === $this->deliveries && [] === $this->pickups;
    }

    public function isDone(): bool
    {
        $delivered = array_filter($this->deliveries, static fn (Delivery $delivery): bool => $delivery->isDelivered());
        $pickedUp = array_filter($this->pickups, fn (Mission $mission): bool => $mission->isPickedUpAt($this->location));
        $actionCount = \count($this->deliveries) + \count($this->pickups);

        return $actionCount > 0 && \count($delivered) + \count($pickedUp) === $actionCount;
    }
}
