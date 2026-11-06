<?php

declare(strict_types=1);

namespace App\Service\Route;

/**
 * Rejoue un ordre d'arrêts posé à la main : à chaque arrêt où un choix existe, celui qui mène au parcours complet
 * le plus court, puis qui respecte le mieux l'ordre posé ; les arrêts devenus inutiles sautent, la recherche complète.
 */
final class RouteReplay
{
    private const string DEAD_END = 'route.error.no_route';

    /** @var array<string, array{path: list<array{location: ?string, done: int}>, skipped: int}|null> null = impasse */
    private array $bestSuffixes = [];

    /** @var list<string> */
    private array $stopOrder = [];

    public function __construct(
        private readonly TaskList $tasks,
        private readonly StopPolicy $policy,
    ) {
    }

    /**
     * @param list<string> $stopOrder
     *
     * @return list<array{location: ?string, done: int}>
     */
    public function replay(?string $origin, int $alreadyDone, array $stopOrder): array
    {
        $this->stopOrder = $stopOrder;
        $starts = null === $origin ? [$alreadyDone] : $this->policy->stopAt($origin, $alreadyDone);
        $best = $this->best(array_map(fn (int $done): ?array => $this->prepend($origin, $done, $this->bestSuffix(0, $origin, $done)), $starts));

        return $best['path'] ?? throw new RouteException(self::DEAD_END);
    }

    /**
     * @return array{path: list<array{location: ?string, done: int}>, skipped: int}|null
     */
    private function bestSuffix(int $index, ?string $location, int $done): ?array
    {
        $key = $index.'|'.$location.'|'.$done;

        if (!\array_key_exists($key, $this->bestSuffixes)) {
            $this->bestSuffixes[$key] = $this->computeSuffix($index, $location, $done);
        }

        return $this->bestSuffixes[$key];
    }

    /**
     * @return array{path: list<array{location: ?string, done: int}>, skipped: int}|null
     */
    private function computeSuffix(int $index, ?string $location, int $done): ?array
    {
        if ($index === \count($this->stopOrder)) {
            return $this->completion($location, $done);
        }

        $next = $this->stopOrder[$index];
        $outcomes = array_filter(array_unique($this->policy->stopAt($next, $done)), static fn (int $outcome): bool => $outcome !== $done);

        if ([] === $outcomes) {
            return $this->skip($this->bestSuffix($index + 1, $location, $done));
        }

        return $this->best(array_map(
            fn (int $outcome): ?array => $this->prepend($next, $outcome, $this->bestSuffix($index + 1, $next, $outcome)),
            array_values($outcomes),
        ));
    }

    /**
     * @param array{path: list<array{location: ?string, done: int}>, skipped: int}|null $suffix
     *
     * @return array{path: list<array{location: ?string, done: int}>, skipped: int}|null
     */
    private function prepend(?string $location, int $done, ?array $suffix): ?array
    {
        return null === $suffix ? null : ['path' => [['location' => $location, 'done' => $done], ...$suffix['path']], 'skipped' => $suffix['skipped']];
    }

    /**
     * @param array{path: list<array{location: ?string, done: int}>, skipped: int}|null $suffix
     *
     * @return array{path: list<array{location: ?string, done: int}>, skipped: int}|null
     */
    private function skip(?array $suffix): ?array
    {
        return null === $suffix ? null : ['path' => $suffix['path'], 'skipped' => $suffix['skipped'] + 1];
    }

    /**
     * @param list<array{path: list<array{location: ?string, done: int}>, skipped: int}|null> $candidates
     *
     * @return array{path: list<array{location: ?string, done: int}>, skipped: int}|null
     */
    private function best(array $candidates): ?array
    {
        $feasible = array_values(array_filter($candidates, static fn (?array $candidate): bool => null !== $candidate));
        usort($feasible, static fn (array $first, array $second): int => [\count($first['path']), $first['skipped']] <=> [\count($second['path']), $second['skipped']]);

        return $feasible[0] ?? null;
    }

    /**
     * @return array{path: list<array{location: ?string, done: int}>, skipped: int}|null
     */
    private function completion(?string $location, int $done): ?array
    {
        if ($this->tasks->isComplete($done)) {
            return ['path' => [], 'skipped' => 0];
        }

        try {
            return ['path' => \array_slice((new RouteSearch($this->tasks, $this->policy))->continueFrom($location, $done), 1), 'skipped' => 0];
        } catch (RouteException $exception) {
            if (self::DEAD_END !== $exception->getMessage()) {
                throw $exception;
            }

            return null;
        }
    }
}
