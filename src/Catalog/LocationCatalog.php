<?php

declare(strict_types=1);

namespace App\Catalog;

final class LocationCatalog
{
    private const string SEPARATOR = ' › ';

    /** @var array<string, array<string, array<string, ?string>>> système → planète → lieu → lune */
    private const array SYSTEMS = [
        'Stanton' => [
            'Hurston' => [
                'Lorville' => null,
                'Everus Harbor' => null,
                'HUR-L1 Green Glade Station' => null,
                'HUR-L2 Faithful Dream Station' => null,
                'HUR-L3 Thundering Express Station' => null,
                'HUR-L4 Melodic Fields Station' => null,
                'HUR-L5 High Course Station' => null,
                'HDMS Bezdek' => 'Arial',
                'HDMS Anderson' => 'Aberdeen',
                'HDMS Hahn' => 'Magda',
            ],
            'Crusader' => [
                'Orison' => null,
                'Seraphim Station' => null,
                'CRU-L1 Ambitious Dream Station' => null,
                'CRU-L4 Shallow Fields Station' => null,
                'CRU-L5 Beautiful Glen Station' => null,
                'Gallete Family Farms' => 'Cellin',
                'Hickes Research Outpost' => 'Cellin',
                'Kudre Ore' => 'Daymar',
                'Bountiful Harvest Hydroponics' => 'Daymar',
                'ArcCorp Mining Area 141' => 'Daymar',
                'Benson Mining Outpost' => 'Yela',
                'Deakins Research Outpost' => 'Yela',
            ],
            'ArcCorp' => [
                'Area 18' => null,
                'Baijini Point' => null,
                'ARC-L1 Wide Forest Station' => null,
                'ARC-L2 Lively Pathway Station' => null,
                'ARC-L3 Modern Express Station' => null,
                'ARC-L4 Faint Glen Station' => null,
                'ARC-L5 Yellow Core Station' => null,
                'Humboldt Mines' => 'Lyria',
                'Shubin Mining SAL-2' => 'Lyria',
                'Shubin Mining SAL-5' => 'Lyria',
            ],
            'microTech' => [
                'New Babbage' => null,
                'Port Tressler' => null,
                'MIC-L1 Shallow Frontier Station' => null,
                'MIC-L2 Long Forest Station' => null,
                'MIC-L3 Endless Odyssey Station' => null,
                'MIC-L4 Red Crossroads Station' => null,
                'MIC-L5 Modern Icarus Station' => null,
                'Rayari Deltana Research Outpost' => null,
                'Rayari Anvik Research Outpost' => 'Calliope',
                'Devlin Scrapyard' => 'Euterpe',
            ],
            'Gateways' => [
                'Pyro Gateway (Stanton)' => null,
                'Nyx Gateway (Stanton)' => null,
            ],
        ],
        'Pyro' => [
            'Pyro I' => [
                'Gray Gardens Depot' => null,
                'Rustville' => null,
                'Stag\'s Rut' => null,
            ],
            'Monox' => [
                'Checkmate' => null,
                'Arid Reach' => null,
                'Jackson\'s Swap' => null,
                'Last Ditch' => null,
                'Ostler\'s Claim' => null,
                'Slowburn Depot' => null,
                'Sunset Mesa' => null,
                'Yang\'s Place' => null,
            ],
            'Bloom' => [
                'Orbituary' => null,
                'Starlight Service Station' => null,
                'Patch City' => null,
                'Shepherd\'s Rest' => null,
                'The Yard' => null,
                'Narena\'s Rest' => null,
                'Shadowfall' => null,
                'Bueno Ravine' => null,
                'Carver\'s Ridge' => null,
                'Windfall' => null,
                'Prospect Depot' => null,
                'Frigid Knot' => null,
                'The Golden Riviera' => null,
            ],
            'Pyro IV' => [
                'Chawla\'s Beach' => null,
                'Dinger\'s Depot' => null,
                'Fallow Field' => null,
                'Goner\'s Deal' => null,
                'Sacren\'s Plot' => null,
            ],
            'Pyro V' => [
                'Gaslight' => null,
                'Rod\'s Fuel \'N Supplies' => null,
                'Rat\'s Nest' => null,
                'Ashland' => 'Ignis',
                'Kabir\'s Post' => 'Ignis',
            ],
            'Terminus' => [
                'Ruin Station' => null,
                'Endgame' => null,
                'Dudley & Daughters' => null,
                'Megumi Refueling' => null,
                'Blackrock Exchange' => null,
                'Bullock\'s Reach' => null,
                'Canard View' => null,
                'Kinder Plots' => null,
                'Last Landings' => null,
                'Rough Landing' => null,
                'Scarper\'s Turn' => null,
                'Stonetree' => null,
                'Supply Gap' => null,
                'Watcher\'s Depot' => null,
            ],
            'Gateways' => [
                'Stanton Gateway (Pyro)' => null,
                'Nyx Gateway (Pyro)' => null,
            ],
        ],
        'Nyx' => [
            'Glaciem Ring' => [
                'Levski' => 'Delamar',
            ],
            'Keeger Belt' => [
                'People\'s Service Station Alpha' => null,
                'People\'s Service Station Delta' => null,
                'People\'s Service Station Lambda' => null,
                'People\'s Service Station Theta' => null,
            ],
            'Gateways' => [
                'Stanton Gateway (Nyx)' => null,
                'Pyro Gateway (Nyx)' => null,
            ],
        ],
    ];

    /** @return list<array{value: string, label: string}> */
    public static function optgroups(): array
    {
        return array_map(
            static fn (string $group): array => ['value' => $group, 'label' => $group],
            array_keys(self::locationsByGroup()),
        );
    }

    /** @return list<array{value: string, text: string, optgroup: string, moon: ?string}> */
    public static function suggestions(): array
    {
        $suggestions = [];
        foreach (self::locationsByGroup() as $group => $locations) {
            array_push($suggestions, ...self::groupSuggestions($group, $locations));
        }

        return $suggestions;
    }

    /** @return array<string, array<string, ?string>> */
    private static function locationsByGroup(): array
    {
        $groups = [];
        foreach (self::SYSTEMS as $system => $planets) {
            $groups += self::planetGroups($system, $planets);
        }

        return $groups;
    }

    /**
     * @param array<string, array<string, ?string>> $planets
     *
     * @return array<string, array<string, ?string>>
     */
    private static function planetGroups(string $system, array $planets): array
    {
        return array_combine(
            array_map(static fn (string $planet): string => $system.self::SEPARATOR.$planet, array_keys($planets)),
            $planets,
        );
    }

    /**
     * @param array<string, ?string> $locations
     *
     * @return list<array{value: string, text: string, optgroup: string, moon: ?string}>
     */
    private static function groupSuggestions(string $group, array $locations): array
    {
        return array_map(
            static fn (string $name, ?string $moon): array => ['value' => $name, 'text' => $name, 'optgroup' => $group, 'moon' => $moon],
            array_keys($locations),
            $locations,
        );
    }
}
