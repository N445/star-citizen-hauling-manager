<?php

declare(strict_types=1);

namespace App\Service\Route;

/**
 * A* sur (lieu courant, tâches faites) : chaque arrêt coûte 1, l'heuristique compte les lieux restant à visiter.
 */
final class RouteSearch
{
    private const int MAX_EXPANSIONS = 300_000;

    /** @var list<array{location: ?string, done: int, cost: int, parent: ?int}> */
    private array $nodes = [];

    /** @var array<string, int> */
    private array $bestCosts = [];

    private \SplPriorityQueue $open;

    public function __construct(
        private readonly TaskList $tasks,
        private readonly StopPolicy $policy,
    ) {
        $this->open = new \SplPriorityQueue();
    }

    /**
     * @return list<array{location: ?string, done: int}>
     */
    public function run(?string $origin, int $alreadyDone): array
    {
        foreach ($this->initialStates($origin, $alreadyDone) as $done) {
            $this->push($origin, $done, 0, null);
        }

        return $this->search();
    }

    /**
     * @return list<array{location: ?string, done: int}> le premier élément est l'état de départ fourni
     */
    public function continueFrom(?string $location, int $done): array
    {
        $this->push($location, $done, 0, null);

        return $this->search();
    }

    /**
     * @return list<array{location: ?string, done: int}>
     */
    private function search(): array
    {
        for ($expansions = 0; !$this->open->isEmpty(); ++$expansions) {
            $nodeId = $this->open->extract();

            if ($this->tasks->isComplete($this->nodes[$nodeId]['done'])) {
                return $this->pathTo($nodeId);
            }

            $this->guard($expansions);
            $this->expand($nodeId);
        }

        throw new RouteException('route.error.no_route');
    }

    /**
     * @return list<int>
     */
    private function initialStates(?string $origin, int $alreadyDone): array
    {
        return null === $origin ? [$alreadyDone] : $this->policy->stopAt($origin, $alreadyDone);
    }

    private function expand(int $nodeId): void
    {
        ['location' => $location, 'done' => $done, 'cost' => $cost] = $this->nodes[$nodeId];

        if ($this->bestCosts[$this->key($location, $done)] < $cost) {
            return;
        }

        foreach (array_diff($this->tasks->pendingLocations($done), [$location]) as $next) {
            $this->expandTowards($nodeId, $next, $done, $cost + 1);
        }
    }

    private function expandTowards(int $parentId, string $next, int $done, int $cost): void
    {
        $outcomes = array_filter($this->policy->stopAt($next, $done), static fn (int $outcome): bool => $outcome !== $done);

        foreach ($outcomes as $outcome) {
            $this->push($next, $outcome, $cost, $parentId);
        }
    }

    private function push(?string $location, int $done, int $cost, ?int $parentId): void
    {
        $key = $this->key($location, $done);

        if (isset($this->bestCosts[$key]) && $this->bestCosts[$key] <= $cost) {
            return;
        }

        $this->bestCosts[$key] = $cost;
        $nodeId = \count($this->nodes);
        $this->nodes[] = ['location' => $location, 'done' => $done, 'cost' => $cost, 'parent' => $parentId];
        $estimate = $cost + $this->tasks->remainingStopsAtLeast($done, $location);
        $this->open->insert($nodeId, [-$estimate, $cost, -$nodeId]);
    }

    /**
     * @return list<array{location: ?string, done: int}>
     */
    private function pathTo(int $nodeId): array
    {
        $path = [];

        for ($current = $nodeId; null !== $current; $current = $this->nodes[$current]['parent']) {
            $path[] = ['location' => $this->nodes[$current]['location'], 'done' => $this->nodes[$current]['done']];
        }

        return array_reverse($path);
    }

    private function key(?string $location, int $done): string
    {
        return $location.'|'.$done;
    }

    private function guard(int $expansions): void
    {
        if ($expansions < self::MAX_EXPANSIONS) {
            return;
        }

        throw new RouteException('route.error.too_many_combinations');
    }
}
