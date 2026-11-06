<?php

declare(strict_types=1);

namespace App\Service\Route;

final readonly class Route
{
    /**
     * @param list<Stop> $stops le premier est le point de départ quand la tournée en a un
     */
    public function __construct(
        public ?string $start,
        public array $stops,
        public bool $customOrder,
        public bool $started,
    ) {
    }

    /**
     * @param list<array{location: ?string, done: int}> $path
     */
    public static function fromPath(TaskList $tasks, array $path, ?string $start, bool $customOrder): self
    {
        $stops = [];
        $previous = 0;

        foreach (array_filter($path, static fn (array $step): bool => null !== $step['location']) as ['location' => $location, 'done' => $done]) {
            $stops[] = Stop::fromTasks($tasks, $location, $tasks->tasksIn($done & ~$previous), $tasks->loadOf($done));
            $previous = $done;
        }

        return new self($start, $stops, $customOrder, 0 !== $tasks->progress());
    }

    public function moveCount(): int
    {
        return \count($this->stops) - (null === $this->start ? 0 : 1);
    }

    /**
     * @return list<string> les arrêts hors départ, dans l'ordre affiché
     */
    public function stopLocations(): array
    {
        $stops = null === $this->start ? $this->stops : \array_slice($this->stops, 1);

        return array_map(static fn (Stop $stop): string => $stop->location, $stops);
    }

    public function isFinished(): bool
    {
        return $this->started && [] === array_filter($this->stops, static fn (Stop $stop): bool => !$stop->isEmpty() && !$stop->isDone());
    }
}
