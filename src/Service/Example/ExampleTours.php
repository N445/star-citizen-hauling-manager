<?php

declare(strict_types=1);

namespace App\Service\Example;

/**
 * Tournées de démonstration, de la plus simple à la plus chargée. Lieux et ressources = noms exacts des catalogues.
 */
final class ExampleTours
{
    private const string HUR_L1 = 'HUR-L1 Green Glade Station';
    private const string HUR_L2 = 'HUR-L2 Faithful Dream Station';
    private const string HUR_L3 = 'HUR-L3 Thundering Express Station';
    private const string HUR_L4 = 'HUR-L4 Melodic Fields Station';
    private const string HUR_L5 = 'HUR-L5 High Course Station';
    private const string ARC_L1 = 'ARC-L1 Wide Forest Station';
    private const string CRU_L1 = 'CRU-L1 Ambitious Dream Station';
    private const string MIC_L1 = 'MIC-L1 Shallow Frontier Station';
    private const string MIC_L2 = 'MIC-L2 Long Forest Station';

    /**
     * @return list<array{name: string, ship: string, start: string, stopOrder?: list<string>, missions: list<array{name: string, reward: int, type: string, pickups: list<string>, deliveries: list<array{string, string, int}>}>}>
     */
    public static function all(): array
    {
        return [self::simple(), self::medium(), self::complex(), self::veryComplex(), self::loadThenDeliver()];
    }

    private static function simple(): array
    {
        return [
            'name' => 'Exemple 1 · Simple',
            'ship' => 'drake-cutlass-black',
            'start' => 'Lorville',
            'missions' => [
                self::mission('Fret médical', 18000, 'direct', ['Lorville'], [['Everus Harbor', 'Medical Supplies', 16]]),
                self::mission('Ravitaillement station', 21000, 'direct', ['Everus Harbor'], [[self::HUR_L1, 'Processed Food', 24]]),
            ],
        ];
    }

    private static function medium(): array
    {
        return [
            'name' => 'Exemple 2 · Moyen',
            'ship' => 'misc-freelancer-max',
            'start' => 'Everus Harbor',
            'missions' => [
                self::mission('Urgence médicale L1', 32000, 'direct', ['Everus Harbor'], [[self::HUR_L1, 'Medical Supplies', 24]]),
                self::mission('Tournée des stations', 46000, 'multiple_drop_off', [self::HUR_L1], [
                    [self::HUR_L2, 'Processed Food', 16],
                    [self::HUR_L3, 'Agricultural Supplies', 24],
                ]),
                self::mission('Collecte des déchets', 39000, 'multiple_pick_up', [self::HUR_L2, self::HUR_L3], [['Lorville', 'Waste', 32]]),
                self::mission('Chantier d\'Everus', 41000, 'direct', ['Lorville'], [['Everus Harbor', 'Construction Materials', 40]]),
            ],
        ];
    }

    private static function complex(): array
    {
        return [
            'name' => 'Exemple 3 · Complexe',
            'ship' => 'drake-caterpillar',
            'start' => 'Area 18',
            'missions' => [
                self::mission('Approvisionnement ArcCorp', 58000, 'multiple_drop_off', ['Area 18'], [
                    ['Baijini Point', 'Processed Food', 32],
                    [self::ARC_L1, 'Medical Supplies', 16],
                ]),
                self::mission('Spiritueux pour Orison', 64000, 'direct', ['Baijini Point'], [['Orison', 'Distilled Spirits', 48]]),
                self::mission('Minerai de Lyria', 96000, 'multiple_pick_up', ['Humboldt Mines', 'Shubin Mining SAL-2'], [['Baijini Point', 'Copper', 96]]),
                self::mission('Semences pour Crusader', 72000, 'direct', [self::ARC_L1], [[self::CRU_L1, 'Agricultural Supplies', 64]]),
                self::mission('Desserte de Cellin', 81000, 'multiple_drop_off', ['Orison'], [
                    ['Seraphim Station', 'Medical Supplies', 24],
                    ['Gallete Family Farms', 'Agricultural Supplies', 40],
                ]),
                self::mission('Titane pour ArcCorp', 88000, 'direct', ['Seraphim Station'], [['Area 18', 'Titanium', 80]]),
                self::mission('Laranite des lunes', 67000, 'multiple_pick_up', ['Kudre Ore', 'Benson Mining Outpost'], [['Seraphim Station', 'Laranite', 32]]),
            ],
        ];
    }

    private static function veryComplex(): array
    {
        return [
            'name' => 'Exemple 4 · Très complexe',
            'ship' => 'crusader-c2-hercules',
            'start' => 'New Babbage',
            'missions' => [
                self::mission('Hôpital de Port Tressler', 52000, 'direct', ['New Babbage'], [['Port Tressler', 'Medical Supplies', 64]]),
                self::mission('Ravitaillement microTech', 74000, 'multiple_drop_off', ['Port Tressler'], [
                    [self::MIC_L1, 'Processed Food', 48],
                    ['Rayari Deltana Research Outpost', 'Medical Supplies', 16],
                ]),
                self::mission('Ferraille de Devlin', 118000, 'multiple_pick_up', [self::MIC_L2, 'Devlin Scrapyard'], [['Port Tressler', 'Scrap', 120]]),
                self::mission('Titane interplanétaire', 135000, 'direct', ['Port Tressler'], [['Baijini Point', 'Titanium', 96]]),
                self::mission('Grand chantier d\'Area 18', 168000, 'multiple_drop_off', ['Baijini Point'], [
                    ['Area 18', 'Construction Materials', 80],
                    [self::ARC_L1, 'Agricultural Supplies', 40],
                    ['Shubin Mining SAL-2', 'Hydrogen', 24],
                ]),
                self::mission('Spiritueux pour Seraphim', 61000, 'direct', ['Area 18'], [['Seraphim Station', 'Distilled Spirits', 32]]),
                self::mission('Quartz de Daymar', 97000, 'multiple_pick_up', ['Kudre Ore', 'ArcCorp Mining Area 141'], [['Seraphim Station', 'Quartz', 64]]),
                self::mission('Stims pour Hurston', 58000, 'direct', ['Seraphim Station'], [['Everus Harbor', 'Stims', 24]]),
                self::mission('Desserte d\'Arial', 76000, 'multiple_drop_off', ['Everus Harbor'], [
                    [self::HUR_L1, 'Agricultural Supplies', 32],
                    ['HDMS Bezdek', 'Processed Food', 24],
                ]),
                self::mission('Aluminium pour Lorville', 142000, 'direct', [self::HUR_L2], [['Lorville', 'Aluminum', 128]]),
                self::mission('Laranite d\'Aberdeen et Magda', 112000, 'multiple_pick_up', ['HDMS Anderson', 'HDMS Hahn'], [['Lorville', 'Laranite', 48]]),
            ],
        ];
    }

    private static function loadThenDeliver(): array
    {
        return [
            'name' => 'Exemple 5 · Chargement puis livraisons',
            'ship' => 'drake-caterpillar',
            'start' => 'Lorville',
            'stopOrder' => ['Everus Harbor', self::HUR_L1, self::HUR_L2, self::HUR_L3, self::HUR_L4, self::HUR_L5, 'HDMS Bezdek'],
            'missions' => [
                self::mission('Hôpital de HUR-L3', 84000, 'direct', ['Lorville'], [[self::HUR_L3, 'Medical Supplies', 96]]),
                self::mission('Vivres et hydrogène', 102000, 'multiple_drop_off', ['Everus Harbor'], [
                    [self::HUR_L4, 'Processed Food', 64],
                    ['HDMS Bezdek', 'Hydrogen', 48],
                ]),
                self::mission('Semences pour HUR-L5', 76000, 'direct', [self::HUR_L1], [[self::HUR_L5, 'Agricultural Supplies', 80]]),
                self::mission('Chantier de Bezdek', 128000, 'direct', [self::HUR_L2], [['HDMS Bezdek', 'Construction Materials', 160]]),
            ],
        ];
    }

    /**
     * @param list<string>                  $pickups
     * @param list<array{string, string, int}> $deliveries
     *
     * @return array{name: string, reward: int, type: string, pickups: list<string>, deliveries: list<array{string, string, int}>}
     */
    private static function mission(string $name, int $reward, string $type, array $pickups, array $deliveries): array
    {
        return ['name' => $name, 'reward' => $reward, 'type' => $type, 'pickups' => $pickups, 'deliveries' => $deliveries];
    }
}
