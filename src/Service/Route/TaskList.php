<?php

declare(strict_types=1);

namespace App\Service\Route;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\Tour;

/**
 * Les tâches de la tournée indexées en bits : un état de recherche n'est qu'un entier (tâches faites).
 */
final class TaskList
{
    private const int MAX_TASKS = 62;

    private int $allTasks = 0;

    /** @var array<string, int> */
    private array $tasksByLocation = [];

    /** @var array<int, int> */
    private array $pickupsByMission = [];

    /** @var array<int, int> */
    private array $deliveriesByMission = [];

    /** @var array<int, array<string, int>> */
    private array $pickupsByMissionAndLocation = [];

    /** @var array<string, list<int>> */
    private array $deliveryIndexesByLocation = [];

    /** @var array<int, array<int, int>> quantité livrée par tâche de livraison, par mission */
    private array $deliveredQuantitiesByMission = [];

    /** @var array<int, int> */
    private array $totalsByMission = [];

    /**
     * @param list<Task>    $tasks
     * @param list<Mission> $missions
     */
    private function __construct(
        private readonly array $tasks,
        private readonly array $missions,
        private readonly int $capacity,
    ) {
        foreach ($tasks as $taskIndex => $task) {
            $this->index($task, $taskIndex);
        }

        $this->totalsByMission = array_map(static fn (Mission $mission): int => $mission->effectiveQuantity(), $missions);
    }

    public static function fromTour(Tour $tour): self
    {
        $missions = $tour->missions()->getValues();
        $tasks = array_merge(...array_map(self::tasksOf(...), $missions, array_keys($missions)));

        if (\count($tasks) > self::MAX_TASKS) {
            throw new RouteException('route.error.too_many_tasks', ['count' => \count($tasks), 'maximum' => self::MAX_TASKS]);
        }

        return new self($tasks, $missions, $tour->capacity());
    }

    public function assertEachMissionFits(): void
    {
        foreach ($this->missions as $mission) {
            $this->assertFits($mission);
        }
    }

    public function progress(): int
    {
        $done = 0;

        foreach ($this->tasks as $taskIndex => $task) {
            $done |= $this->isAlreadyDone($task) ? 1 << $taskIndex : 0;
        }

        return $done;
    }

    public function isComplete(int $done): bool
    {
        return $done === $this->allTasks;
    }

    /**
     * @return list<string>
     */
    public function pendingLocations(int $done): array
    {
        return array_keys(array_filter($this->tasksByLocation, static fn (int $tasks): bool => 0 !== ($tasks & ~$done)));
    }

    public function remainingStopsAtLeast(int $done, ?string $currentLocation): int
    {
        return \count(array_diff($this->pendingLocations($done), [$currentLocation]));
    }

    public function deliverAt(string $location, int $done): int
    {
        foreach ($this->deliveryIndexesByLocation[$location] ?? [] as $taskIndex) {
            $done |= $this->deliveryIfLoaded($taskIndex, $done);
        }

        return $done;
    }

    /**
     * @return list<int>
     */
    public function missionsToPickUpAt(string $location, int $done): array
    {
        return array_keys(array_filter(
            $this->pickupsByMissionAndLocation,
            static fn (array $pickupsByLocation): bool => 0 !== (($pickupsByLocation[$location] ?? 0) & ~$done),
        ));
    }

    public function pickUpIfItFits(int $missionIndex, string $location, int $done): int
    {
        if (!$this->isStarted($missionIndex, $done) && $this->loadOf($done) + $this->totalOf($missionIndex) > $this->capacity) {
            return $done;
        }

        return $done | ($this->pickupsByMissionAndLocation[$missionIndex][$location] ?? 0);
    }

    public function missionInProgress(int $done): ?int
    {
        foreach (array_keys($this->missions) as $missionIndex) {
            if ($this->isStarted($missionIndex, $done) && !$this->isFinished($missionIndex, $done)) {
                return $missionIndex;
            }
        }

        return null;
    }

    public function loadOf(int $done): int
    {
        return array_sum(array_map(fn (int $missionIndex): int => $this->loadOfMission($missionIndex, $done), array_keys($this->missions)));
    }

    /**
     * Ce qui est vraiment à bord, pour l'affichage : un ramassage multiple ne compte que les lieux déjà ramassés. Le calcul
     * du parcours garde {@see loadOf()}, qui réserve toute la mission dès son début (sinon une autre mission prendrait la
     * place des caisses encore à ramasser).
     */
    public function aboardOf(int $done): int
    {
        return array_sum(array_map(fn (int $missionIndex): int => $this->aboardOfMission($missionIndex, $done), array_keys($this->missions)));
    }

    /**
     * @return list<Task>
     */
    public function tasksIn(int $done): array
    {
        return array_values(array_filter($this->tasks, static fn (Task $task, int $index): bool => 0 !== ($done & (1 << $index)), ARRAY_FILTER_USE_BOTH));
    }

    public function mission(int $missionIndex): Mission
    {
        return $this->missions[$missionIndex];
    }

    /**
     * @return list<Task>
     */
    private static function tasksOf(Mission $mission, int $missionIndex): array
    {
        $pickups = array_map(static fn (string $location): Task => Task::pickup($missionIndex, $location), array_values(array_unique($mission->pickups())));
        $deliveries = array_map(static fn (Delivery $delivery): Task => Task::delivery($missionIndex, $delivery), $mission->deliveries()->getValues());

        return [...$pickups, ...$deliveries];
    }

    private function index(Task $task, int $taskIndex): void
    {
        $bit = 1 << $taskIndex;
        $this->allTasks |= $bit;
        $this->tasksByLocation[$task->location] = ($this->tasksByLocation[$task->location] ?? 0) | $bit;

        if ($task->isPickup()) {
            $this->indexPickup($task, $bit);

            return;
        }

        $this->deliveriesByMission[$task->missionIndex] = ($this->deliveriesByMission[$task->missionIndex] ?? 0) | $bit;
        $this->deliveryIndexesByLocation[$task->location][] = $taskIndex;
        $this->deliveredQuantitiesByMission[$task->missionIndex][$taskIndex] = $task->delivery->effectiveQuantity();
    }

    private function indexPickup(Task $task, int $bit): void
    {
        $this->pickupsByMission[$task->missionIndex] = ($this->pickupsByMission[$task->missionIndex] ?? 0) | $bit;
        $this->pickupsByMissionAndLocation[$task->missionIndex][$task->location] = $bit;
    }

    private function isAlreadyDone(Task $task): bool
    {
        return $task->isPickup() ? $this->missions[$task->missionIndex]->isPickedUpAt($task->location) : $task->delivery->isDelivered();
    }

    private function deliveryIfLoaded(int $taskIndex, int $done): int
    {
        $missionPickups = $this->pickupsByMission[$this->tasks[$taskIndex]->missionIndex];

        return ($done & $missionPickups) === $missionPickups ? 1 << $taskIndex : 0;
    }

    private function isStarted(int $missionIndex, int $done): bool
    {
        return 0 !== ($done & $this->pickupsByMission[$missionIndex]);
    }

    private function isFinished(int $missionIndex, int $done): bool
    {
        $deliveries = $this->deliveriesByMission[$missionIndex];

        return ($done & $deliveries) === $deliveries;
    }

    private function loadOfMission(int $missionIndex, int $done): int
    {
        if (!$this->isStarted($missionIndex, $done)) {
            return 0;
        }

        $quantities = $this->deliveredQuantitiesByMission[$missionIndex];
        $delivered = array_filter($quantities, static fn (int $taskIndex): bool => 0 !== ($done & (1 << $taskIndex)), ARRAY_FILTER_USE_KEY);

        return $this->totalOf($missionIndex) - array_sum($delivered);
    }

    private function aboardOfMission(int $missionIndex, int $done): int
    {
        $pickedUp = array_keys(array_filter($this->pickupsByMissionAndLocation[$missionIndex], static fn (int $bit): bool => 0 !== ($done & $bit)));
        $loaded = $this->missions[$missionIndex]->quantityLoadedFrom(array_map('strval', $pickedUp));

        return max(0, $loaded - ($this->totalOf($missionIndex) - $this->loadOfMission($missionIndex, $done)));
    }

    private function totalOf(int $missionIndex): int
    {
        return $this->totalsByMission[$missionIndex];
    }

    private function assertFits(Mission $mission): void
    {
        if ($mission->effectiveQuantity() <= $this->capacity) {
            return;
        }

        throw new RouteException('route.error.mission_exceeds_capacity', ['quantity' => $mission->effectiveQuantity(), 'capacity' => $this->capacity]);
    }
}
