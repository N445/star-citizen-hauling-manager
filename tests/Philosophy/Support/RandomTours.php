<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\DoorSide;
use App\Entity\MissionType;
use Random\Engine\Mt19937;
use Random\Randomizer;

final readonly class RandomTours
{
    private const array LOCATIONS = [
        'Lorville',
        'Everus Harbor',
        'HUR-L1 Green Glade Station',
        'HUR-L2 Faithful Dream Station',
        'HUR-L3 Thundering Express Station',
        'HUR-L4 Melodic Fields Station',
        'Orison',
        'Seraphim Station',
        'Area 18',
        'Baijini Point',
        'New Babbage',
        'Port Tressler',
    ];

    private const array CAPACITY_PROFILES = ['large', 'moyenne', 'serrée'];

    private const array FILL_RATES = [10, 20, 30, 45, 70, 90, 100];

    private Randomizer $random;

    public function __construct(private int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function routeTour(int $maximumMissions): TourBlueprint
    {
        $locations = $this->locations($this->random->getInt(3, 7));
        $missions = $this->missions($locations, $this->random->getInt(1, $maximumMissions), 60);
        $profile = $this->pick(self::CAPACITY_PROFILES);
        $start = $this->start($locations);
        $name = sprintf('graine %d · %d missions · %d lieux · capacité %s · %s', $this->seed, \count($missions), \count($locations), $profile, $this->startLabel($start));

        return new TourBlueprint($name, $this->capacity($profile, $missions), $start, $missions);
    }

    public function cargoTour(): TourBlueprint
    {
        $ship = $this->ship();
        $fillRate = $this->pick(self::FILL_RATES);
        $locations = $this->locations($this->random->getInt(3, 6));
        $missionCount = $this->random->getInt(1, 5);
        $budget = max(1, intdiv($ship->volume() * $fillRate, 100));
        $missions = $this->missions($locations, $missionCount, max(1, intdiv($budget, $missionCount)));
        $start = $this->start($locations);
        $name = sprintf('graine %d · %d missions · %s · remplissage visé %d %% · %s', $this->seed, $missionCount, $ship->label(), $fillRate, $this->startLabel($start));

        return new TourBlueprint($name, $ship->volume(), $start, $missions, $ship);
    }

    /**
     * @param list<string> $items
     *
     * @return list<string>
     */
    public function shuffled(array $items): array
    {
        return $this->random->shuffleArray($items);
    }

    public function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    private function ship(): ShipBlueprint
    {
        $key = $this->pick(ShipFixtures::keys());
        $doorOptions = [
            ShipFixtures::officialDoors($key),
            [DoorSide::Rear],
            [DoorSide::Front],
            [DoorSide::Left],
            [DoorSide::Right],
            [DoorSide::Left, DoorSide::Right],
            [DoorSide::Rear, DoorSide::Left],
            [DoorSide::Top],
        ];

        return new ShipBlueprint($key, $doorOptions[$this->random->getInt(0, \count($doorOptions) - 1)]);
    }

    /**
     * @return list<string>
     */
    private function locations(int $count): array
    {
        return \array_slice($this->random->shuffleArray(self::LOCATIONS), 0, $count);
    }

    /**
     * @param list<string> $locations
     *
     * @return list<MissionBlueprint>
     */
    private function missions(array $locations, int $count, int $budgetPerMission): array
    {
        return array_map(fn (): MissionBlueprint => $this->mission($locations, $budgetPerMission), range(1, $count));
    }

    /**
     * @param list<string> $locations
     */
    private function mission(array $locations, int $budget): MissionBlueprint
    {
        $type = $this->pick([MissionType::Direct, MissionType::Direct, MissionType::MultipleDropOff, MissionType::MultiplePickUp]);
        $endpoints = $this->random->shuffleArray($locations);
        $extra = $this->random->getInt(2, min(3, \count($locations) - 1));

        return match ($type) {
            MissionType::Direct => new MissionBlueprint($type, [$endpoints[0]], $this->drops([$endpoints[1]], $budget)),
            MissionType::MultipleDropOff => new MissionBlueprint($type, [$endpoints[0]], $this->drops(\array_slice($endpoints, 1, $extra), $budget)),
            MissionType::MultiplePickUp => new MissionBlueprint($type, \array_slice($endpoints, 1, $extra), $this->drops([$endpoints[0]], $budget)),
        };
    }

    /**
     * @param list<string> $dropoffs
     *
     * @return list<array{string, int}>
     */
    private function drops(array $dropoffs, int $budget): array
    {
        $share = max(1, intdiv($budget, \count($dropoffs)));

        return array_map(fn (string $dropoff): array => [$dropoff, $this->random->getInt(max(1, intdiv($share, 2)), $share)], $dropoffs);
    }

    /**
     * @param list<string> $locations
     */
    private function start(array $locations): ?string
    {
        $candidates = [null, null, null, ...$locations, 'Port Olisar'];

        return $candidates[$this->random->getInt(0, \count($candidates) - 1)];
    }

    private function startLabel(?string $start): string
    {
        return null === $start ? 'sans départ' : 'départ '.$start;
    }

    /**
     * @param list<MissionBlueprint> $missions
     */
    private function capacity(string $profile, array $missions): int
    {
        $totals = array_map(static fn (MissionBlueprint $mission): int => $mission->total(), $missions);

        return match ($profile) {
            'large' => array_sum($totals),
            'moyenne' => max(max($totals), intdiv(array_sum($totals), 2)),
            'serrée' => max($totals),
        };
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random->getInt(0, \count($items) - 1)];
    }
}
