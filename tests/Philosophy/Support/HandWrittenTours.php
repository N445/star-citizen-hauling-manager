<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\DoorSide;
use App\Entity\MissionType;

final class HandWrittenTours
{
    public const string EVERUS = 'Everus Harbor';

    public const string HUR_L1 = 'HUR-L1 Green Glade Station';

    public const string HUR_L2 = 'HUR-L2 Faithful Dream Station';

    public const string HUR_L3 = 'HUR-L3 Thundering Express Station';

    private const array DESTINATIONS = ['Area 18', 'Orison', 'New Babbage', 'Port Tressler', 'Baijini Point', 'Seraphim Station'];

    public static function userReference(): TourBlueprint
    {
        return new TourBlueprint('cas de référence · départ Everus Harbor · 4 missions', 400, self::EVERUS, [
            new MissionBlueprint(MissionType::MultipleDropOff, [self::EVERUS], [[self::HUR_L1, 20], [self::HUR_L2, 20]]),
            MissionBlueprint::direct(self::HUR_L2, self::HUR_L3, 30),
            MissionBlueprint::direct(self::HUR_L3, self::HUR_L1, 30),
            MissionBlueprint::direct(self::HUR_L1, self::EVERUS, 30),
        ]);
    }

    public static function loadEverythingThenDeliver(int $missionCount, int $quantity, ?ShipBlueprint $ship = null): TourBlueprint
    {
        $missions = array_map(static fn (string $destination): MissionBlueprint => MissionBlueprint::direct('Lorville', $destination, $quantity), \array_slice(self::DESTINATIONS, 0, $missionCount));
        $capacity = $ship?->volume() ?? $missionCount * $quantity;
        $name = sprintf('tout charger à Lorville puis tout livrer · %d missions de %d SCU%s', $missionCount, $quantity, null === $ship ? '' : ' · '.$ship->label());

        return new TourBlueprint($name, $capacity, null, $missions, $ship);
    }

    public static function tightCapacity(): TourBlueprint
    {
        return new TourBlueprint('capacité très serrée · chaque mission remplit la soute', 50, 'Lorville', [
            MissionBlueprint::direct('Lorville', 'Area 18', 50),
            MissionBlueprint::direct('Area 18', 'Orison', 50),
            MissionBlueprint::direct('Lorville', 'Orison', 50),
            MissionBlueprint::direct('Orison', 'Lorville', 50),
        ]);
    }

    public static function multiplePickups(): TourBlueprint
    {
        return new TourBlueprint('ramassages multiples · trois lieux vers Area 18', 100, null, [
            new MissionBlueprint(MissionType::MultiplePickUp, ['Lorville', 'Orison', 'New Babbage'], [['Area 18', 60]]),
            MissionBlueprint::direct('Orison', 'Lorville', 30),
        ]);
    }

    public static function greedyTrap(): TourBlueprint
    {
        return new TourBlueprint('piège glouton · 50 SCU · ramassage multiple de 50 SCU et petite mission au même lieu', 50, null, [
            new MissionBlueprint(MissionType::MultiplePickUp, ['Orison', 'Lorville'], [['Area 18', 50]]),
            MissionBlueprint::direct('Lorville', 'Orison', 20),
        ]);
    }

    /**
     * @param list<DoorSide> $doors
     */
    public static function cargoOn(string $shipKey, array $doors, int $missionCount, int $quantity): TourBlueprint
    {
        return self::loadEverythingThenDeliver($missionCount, $quantity, new ShipBlueprint($shipKey, $doors));
    }

    public static function nearlyFull(string $shipKey): TourBlueprint
    {
        $ship = new ShipBlueprint($shipKey, ShipFixtures::officialDoors($shipKey));
        $volume = $ship->volume();
        $missions = [
            new MissionBlueprint(MissionType::MultipleDropOff, ['Lorville'], [['Area 18', intdiv($volume, 3)], ['Orison', intdiv($volume, 3)]]),
            MissionBlueprint::direct('Lorville', 'New Babbage', $volume - 2 * intdiv($volume, 3)),
        ];

        return new TourBlueprint(sprintf('soute pleine à 100 %% · %s', $ship->label()), $volume, null, $missions, $ship);
    }
}
