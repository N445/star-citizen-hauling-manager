<?php

declare(strict_types=1);

namespace App\Twig\Components\Plan;

use App\Domain\CargoLot;
use App\Domain\Location;
use App\Domain\CargoPlacer;
use App\Domain\DoorSide;
use App\Domain\Mission;
use App\Domain\MissionType;
use App\Domain\Pin;
use App\Domain\RouteOptimizer;
use App\Domain\ShipCatalog;
use App\Domain\StantonLocations;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class PlanFactory
{
    public const COLORS = ['#3fb7ff', '#c77dff', '#ffd166', '#ef476f', '#06d6a0', '#f78c6b', '#9ad1d4', '#b5e48c'];

    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * Routing and placement are deterministic: the result is cached so stepping through stops stays cheap.
     *
     * @param array<string, mixed> $input {ship: string, start: string, missions: list<{label, reward, lots: list<{resource, scu, pickup, dropoff}>}>}
     *
     * @throws \InvalidArgumentException|\DomainException when the input cannot be turned into a route
     */
    public function build(array $input): Plan
    {
        $payload = [
            'ship' => $input['ship'] ?? '',
            'start' => $input['start'] ?? '',
            'missions' => array_map(static function (mixed $mission): mixed {
                if (!\is_array($mission)) {
                    return $mission;
                }
                unset($mission['uid']);
                $mission['lots'] = array_map(static function (mixed $lot): mixed {
                    unset($lot['uid']);

                    return $lot;
                }, $mission['lots'] ?? []);

                return $mission;
            }, array_values($input['missions'] ?? [])),
            'door' => $input['door'] ?? DoorSide::Rear->value,
            'pins' => $input['pins'] ?? [],
        ];
        $key = 'plan_'.sha1(self::domainVersion().json_encode($payload, JSON_THROW_ON_ERROR));

        return $this->cache->get($key, function (ItemInterface $item) use ($payload): Plan {
            $item->expiresAfter(3600);

            return $this->compute($payload);
        });
    }

    /** Cached plans embed domain objects: any change of the domain code or data invalidates them. */
    private static function domainVersion(): string
    {
        static $version = null;

        return $version ??= sha1(implode(',', array_map(
            static fn (string $file): string => $file.filemtime($file),
            glob(\dirname(__DIR__, 3).'/Domain/{,Data/}*', GLOB_BRACE | GLOB_NOSORT) ?: [],
        )));
    }

    /** @param array<string, mixed> $input */
    private function compute(array $input): Plan
    {
        $ship = ShipCatalog::get((string) $input['ship'])->withDoor(DoorSide::tryFrom((string) $input['door']) ?? DoorSide::Rear);
        $start = StantonLocations::get((string) $input['start']);

        $missions = [];
        $missionMeta = [];
        $lotMeta = [];
        foreach (array_values($input['missions']) as $i => $row) {
            $missionId = 'm'.($i + 1);
            $label = trim((string) ($row['label'] ?? '')) ?: 'Mission '.($i + 1);
            $type = MissionType::tryFrom((string) ($row['type'] ?? '')) ?? MissionType::Direct;
            $sharedPickup = self::location($row['pickup'] ?? null);
            $sharedDrop = self::location($row['dropoff'] ?? null);
            $lots = [];
            foreach (array_values($row['lots'] ?? []) as $j => $lotRow) {
                $scu = (int) ($lotRow['scu'] ?? 0);
                if ($scu < 1) {
                    continue;
                }
                // The type decides which fields are shared by the mission and which belong to each lot.
                $pickup = match ($type) {
                    MissionType::Direct, MissionType::MultipleDropOff => $sharedPickup,
                    default => self::location($lotRow['pickup'] ?? null),
                } ?? throw new \InvalidArgumentException('Choisis un lieu de ramassage.');
                $dropoff = match ($type) {
                    MissionType::Direct, MissionType::MultiplePickUp, MissionType::FreeShopping => $sharedDrop,
                    MissionType::MultipleDropOff => self::location($lotRow['dropoff'] ?? null),
                };
                $resource = trim((string) ($lotRow['resource'] ?? '')) ?: 'Cargaison';
                $lotId = $missionId.'-l'.($j + 1);
                $lots[] = new CargoLot($lotId, $missionId, $resource, $scu, $pickup, $dropoff);
                $lotMeta[$lotId] = ['missionId' => $missionId, 'resource' => $resource, 'pickup' => $pickup->name, 'dropoff' => $dropoff?->name ?? ''];
            }
            if ([] === $lots) {
                continue;
            }
            $maxSize = max(1, min(32, (int) ($row['maxSize'] ?? 32)));
            $missions[] = new Mission($missionId, $label, max(0, (int) ($row['reward'] ?? 0)), $lots, $type, $maxSize);
            $missionMeta[$missionId] = ['label' => $label, 'color' => self::COLORS[$i % \count(self::COLORS)], 'type' => $type->value, 'maxSize' => $maxSize];
        }

        if ([] === $missions) {
            throw new \InvalidArgumentException('Ajoute au moins un lot avec une quantité de SCU.');
        }

        $route = (new RouteOptimizer())->optimize($missions, $ship, $start);
        $pins = [];
        foreach ($input['pins'] as $crateId => $pin) {
            $pins[] = new Pin((string) $crateId, (int) $pin['x'], (int) $pin['y'], (int) $pin['z'], (int) ($pin['o'] ?? 0));
        }
        $placement = (new CargoPlacer())->place($route, $ship, $pins);

        return new Plan($route, $placement, $missionMeta, $lotMeta);
    }

    private static function location(mixed $id): ?Location
    {
        return \is_string($id) && '' !== $id ? StantonLocations::get($id) : null;
    }
}
