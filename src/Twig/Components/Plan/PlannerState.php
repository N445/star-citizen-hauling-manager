<?php

declare(strict_types=1);

namespace App\Twig\Components\Plan;

use App\Domain\Crate;
use App\Domain\DoorSide;
use App\Domain\MissionType;
use App\Domain\ShipCatalog;
use App\Domain\StantonLocations;

/** Turns untrusted data (browser storage, forged requests) into a state the planner can always render. */
final class PlannerState
{
    public const DEFAULT_START = 'area18';
    public const MAX_MISSIONS = 30;
    public const MAX_LOTS = 30;

    /**
     * @return array{ship: string, start: string, calculated: bool, missions: list<array<string, mixed>>, doors: array<string, string>, pins: array<string, array{x: int, y: int, z: int, o: int}>}
     */
    public static function normalize(mixed $raw): array
    {
        $raw = \is_array($raw) ? $raw : [];
        $locations = StantonLocations::all();

        $ship = \is_string($raw['ship'] ?? null) && isset(ShipCatalog::all()[$raw['ship']]) ? $raw['ship'] : ShipCatalog::DEFAULT;
        $start = \is_string($raw['start'] ?? null) && isset($locations[$raw['start']]) ? $raw['start'] : self::DEFAULT_START;

        $missions = [];
        foreach (\is_array($raw['missions'] ?? null) ? array_slice(array_values($raw['missions']), 0, self::MAX_MISSIONS) : [] as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $lots = [];
            foreach (\is_array($row['lots'] ?? null) ? array_slice(array_values($row['lots']), 0, self::MAX_LOTS) : [] as $lot) {
                if (!\is_array($lot)) {
                    continue;
                }
                $lots[] = [
                    'uid' => self::uid($lot['uid'] ?? null),
                    'resource' => self::text($lot['resource'] ?? '', 60),
                    'scu' => max(0, min(100000, (int) ($lot['scu'] ?? 0))),
                    'pickup' => \is_string($lot['pickup'] ?? null) && isset($locations[$lot['pickup']]) ? $lot['pickup'] : 'area18',
                    'dropoff' => \is_string($lot['dropoff'] ?? null) && isset($locations[$lot['dropoff']]) ? $lot['dropoff'] : 'lorville',
                ];
            }
            $type = \is_string($row['type'] ?? null) ? (MissionType::tryFrom($row['type']) ?? MissionType::Direct) : MissionType::Direct;
            $shared = $row['dropoff'] ?? 'lorville';
            $missions[] = [
                'uid' => self::uid($row['uid'] ?? null),
                'label' => self::text($row['label'] ?? '', 80),
                'reward' => max(0, min(2_000_000_000, (int) ($row['reward'] ?? 0))),
                'type' => $type->value,
                'appliedType' => $type->value,
                'maxSize' => self::maxSize($row['maxSize'] ?? 32),
                'pickup' => \is_string($row['pickup'] ?? null) && isset($locations[$row['pickup']]) ? $row['pickup'] : 'area18',
                'dropoff' => \is_string($shared) && (isset($locations[$shared]) || ('' === $shared && MissionType::FreeShopping === $type)) ? $shared : 'lorville',
                'lots' => [] === $lots ? [self::emptyLot()] : $lots,
            ];
        }

        return [
            'ship' => $ship,
            'start' => $start,
            'calculated' => true === ($raw['calculated'] ?? false),
            'doors' => self::doors($raw['doors'] ?? null),
            'pins' => self::pins($raw['pins'] ?? null),
            'missions' => [] === $missions ? [self::emptyMission()] : $missions,
        ];
    }

    /**
     * The input of a plan (what RouteStepper and PlanFactory consume) from a planner state.
     *
     * @param array<string, mixed> $state a normalized state
     *
     * @return array<string, mixed>
     */
    public static function planInput(array $state): array
    {
        return [
            'ship' => $state['ship'],
            'start' => $state['start'],
            'missions' => $state['missions'],
            'door' => $state['doors'][$state['ship']] ?? DoorSide::Rear->value,
            'pins' => $state['pins'],
        ];
    }

    /**
     * Sanitizes a plan input coming from the browser (placement endpoints).
     *
     * @return array<string, mixed>
     */
    public static function normalizePlanInput(mixed $raw): array
    {
        $raw = \is_array($raw) ? $raw : [];
        $state = self::normalize($raw);
        $input = self::planInput($state);
        $input['door'] = \is_string($raw['door'] ?? null) && null !== DoorSide::tryFrom($raw['door']) ? $raw['door'] : DoorSide::Rear->value;

        return $input;
    }

    /** @return array<string, string> */
    private static function doors(mixed $raw): array
    {
        $doors = [];
        foreach (\is_array($raw) ? $raw : [] as $slug => $door) {
            if (\is_string($slug) && isset(ShipCatalog::all()[$slug]) && \is_string($door) && null !== DoorSide::tryFrom($door)) {
                $doors[$slug] = $door;
            }
        }

        return $doors;
    }

    /** @return array<string, array{x: int, y: int, z: int, o: int}> */
    private static function pins(mixed $raw): array
    {
        $pins = [];
        foreach (\is_array($raw) ? array_slice($raw, 0, 1000, true) : [] as $id => $pin) {
            if (\is_string($id) && 1 === preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) && \is_array($pin)) {
                $pins[$id] = [
                    'x' => max(0, min(500, (int) ($pin['x'] ?? 0))),
                    'y' => max(0, min(500, (int) ($pin['y'] ?? 0))),
                    'z' => max(0, min(500, (int) ($pin['z'] ?? 0))),
                    'o' => max(0, min(5, (int) ($pin['o'] ?? 0))),
                ];
            }
        }

        return $pins;
    }

    /** @return array<string, mixed> */
    public static function emptyMission(): array
    {
        return [
            'uid' => self::uid(null),
            'label' => '',
            'reward' => 0,
            'type' => MissionType::Direct->value,
            'appliedType' => MissionType::Direct->value,
            'maxSize' => 32,
            'pickup' => 'area18',
            'dropoff' => 'lorville',
            'lots' => [self::emptyLot()],
        ];
    }

    /** @return array<string, mixed> */
    public static function emptyLot(): array
    {
        return ['uid' => self::uid(null), 'resource' => 'Laranite', 'scu' => 16, 'pickup' => 'area18', 'dropoff' => 'lorville'];
    }

    /** Largest crate size, rounded down to a standard size (the domain does the same). */
    private static function maxSize(mixed $value): int
    {
        $value = max(1, min(32, (int) $value));

        return max(array_filter(Crate::SIZES, static fn (int $size): bool => $size <= $value));
    }

    /**
     * Carries the values that stay meaningful when a mission changes type: shared fields become per-lot
     * fields and the other way round (the first lot gives the shared value).
     *
     * @param array<string, mixed> $mission
     *
     * @return array<string, mixed>
     */
    public static function adaptType(array $mission): array
    {
        $old = MissionType::tryFrom((string) ($mission['appliedType'] ?? '')) ?? MissionType::Direct;
        $new = MissionType::tryFrom((string) ($mission['type'] ?? '')) ?? MissionType::Direct;
        $mission['appliedType'] = $new->value;
        $mission['type'] = $new->value;
        if ($old === $new || [] === ($mission['lots'] ?? [])) {
            return $mission;
        }

        $sharedPickup = static fn (MissionType $t): bool => \in_array($t, [MissionType::Direct, MissionType::MultipleDropOff], true);
        $sharedDrop = static fn (MissionType $t): bool => \in_array($t, [MissionType::Direct, MissionType::MultiplePickUp, MissionType::FreeShopping], true);

        if ($sharedPickup($old) && !$sharedPickup($new)) {
            foreach ($mission['lots'] as $i => $lot) {
                $mission['lots'][$i]['pickup'] = $mission['pickup'];
            }
        } elseif (!$sharedPickup($old) && $sharedPickup($new)) {
            $mission['pickup'] = $mission['lots'][0]['pickup'];
        }

        if ($sharedDrop($old) && !$sharedDrop($new)) {
            foreach ($mission['lots'] as $i => $lot) {
                $mission['lots'][$i]['dropoff'] = '' !== $mission['dropoff'] ? $mission['dropoff'] : 'lorville';
            }
        } elseif (!$sharedDrop($old) && $sharedDrop($new)) {
            $mission['dropoff'] = $mission['lots'][0]['dropoff'];
        }
        if (MissionType::FreeShopping !== $new && '' === $mission['dropoff']) {
            $mission['dropoff'] = 'lorville';
        }

        return $mission;
    }

    private static function uid(mixed $value): string
    {
        return \is_string($value) && 1 === preg_match('/^[a-f0-9]{8}$/', $value) ? $value : bin2hex(random_bytes(4));
    }

    private static function text(mixed $value, int $max): string
    {
        return \is_scalar($value) ? mb_substr(trim((string) $value), 0, $max) : '';
    }
}
