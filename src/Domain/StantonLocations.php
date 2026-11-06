<?php

declare(strict_types=1);

namespace App\Domain;

final class StantonLocations
{
    /** @var array<string, Location>|null */
    private static ?array $byId = null;

    /** @return array<string, Location> indexed by id */
    public static function all(): array
    {
        return self::$byId ??= self::build();
    }

    public static function get(string $id): Location
    {
        return self::all()[$id] ?? throw new \InvalidArgumentException(sprintf('Unknown location "%s".', $id));
    }

    /** @return array<string, Location> */
    private static function build(): array
    {
        $rows = [
            ['orison', 'Orison', 'Crusader', LocationType::Surface, null],
            ['seraphim-station', 'Seraphim Station', 'Crusader', LocationType::Orbital, null],
            ['cru-l1', 'CRU-L1 Ambitious Dream Station', 'Crusader', LocationType::Lagrange, 'CRU-L1'],
            ['gallete-family-farms', 'Gallete Family Farms', 'Crusader', LocationType::Moon, 'Cellin'],
            ['hickes-research-outpost', 'Hickes Research Outpost', 'Crusader', LocationType::Moon, 'Cellin'],
            ['kudre-ore', 'Kudre Ore', 'Crusader', LocationType::Moon, 'Daymar'],
            ['bountiful-harvest', 'Bountiful Harvest Hydroponics', 'Crusader', LocationType::Moon, 'Daymar'],
            ['benson-mining', 'Benson Mining Outpost', 'Crusader', LocationType::Moon, 'Yela'],
            ['deakins-research', 'Deakins Research Outpost', 'Crusader', LocationType::Moon, 'Yela'],
            ['area18', 'Area 18', 'ArcCorp', LocationType::Surface, null],
            ['baijini-point', 'Baijini Point', 'ArcCorp', LocationType::Orbital, null],
            ['arc-l1', 'ARC-L1 Wide Forest Station', 'ArcCorp', LocationType::Lagrange, 'ARC-L1'],
            ['humboldt-mines', 'Humboldt Mines', 'ArcCorp', LocationType::Moon, 'Lyria'],
            ['shubin-sal-2', 'Shubin Mining SAL-2', 'ArcCorp', LocationType::Moon, 'Lyria'],
            ['arccorp-mining-141', 'ArcCorp Mining Area 141', 'ArcCorp', LocationType::Moon, 'Wala'],
            ['shubin-sal-5', 'Shubin Mining SAL-5', 'ArcCorp', LocationType::Moon, 'Wala'],
            ['lorville', 'Lorville', 'Hurston', LocationType::Surface, null],
            ['everus-harbor', 'Everus Harbor', 'Hurston', LocationType::Orbital, null],
            ['hur-l1', 'HUR-L1 Green Glade Station', 'Hurston', LocationType::Lagrange, 'HUR-L1'],
            ['hdms-bezdek', 'HDMS Bezdek', 'Hurston', LocationType::Moon, 'Arial'],
            ['hdms-anderson', 'HDMS Anderson', 'Hurston', LocationType::Moon, 'Aberdeen'],
            ['hdms-hahn', 'HDMS Hahn', 'Hurston', LocationType::Moon, 'Magda'],
            ['new-babbage', 'New Babbage', 'Microtech', LocationType::Surface, null],
            ['port-tressler', 'Port Tressler', 'Microtech', LocationType::Orbital, null],
            ['mic-l1', 'MIC-L1 Shallow Frontier Station', 'Microtech', LocationType::Lagrange, 'MIC-L1'],
            ['rayari-deltana', 'Rayari Deltana Research Outpost', 'Microtech', LocationType::Moon, 'Calliope'],
            ['rayari-anvik', 'Rayari Anvik Research Outpost', 'Microtech', LocationType::Moon, 'Clio'],
            ['devlin-scrapyard', 'Devlin Scrapyard', 'Microtech', LocationType::Moon, 'Euterpe'],
        ];

        $locations = [];
        foreach ($rows as [$id, $name, $planet, $type, $body]) {
            $locations[$id] = new Location($id, $name, 'Stanton', $planet, $type, $body);
        }

        return $locations;
    }
}
