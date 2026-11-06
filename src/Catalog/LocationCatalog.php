<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Entity\Location;
use App\Repository\LocationRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Suggestions de lieux pour Tom Select, tirées de l'arbre `Location` (géré dans l'admin).
 * Seuls les lieux sans enfant sont proposés ; groupe = « Système › Planète », le reste du chemin (la lune) en détail.
 */
final class LocationCatalog implements ResetInterface
{
    private const string SEPARATOR = ' › ';
    private const int GROUP_DEPTH = 2;

    /** @var array{optgroups: list<array{value: string, label: string}>, suggestions: list<array{value: string, text: string, optgroup: string, moon: ?string}>}|null */
    private ?array $catalog = null;

    public function __construct(private readonly LocationRepository $locationRepository)
    {
    }

    /** @return list<array{value: string, label: string}> */
    public function optgroups(): array
    {
        return $this->catalog()['optgroups'];
    }

    /** @return list<array{value: string, text: string, optgroup: string, moon: ?string}> */
    public function suggestions(): array
    {
        return $this->catalog()['suggestions'];
    }

    // Le service vit d'une requête à l'autre en mode worker : on oublie le cache pour voir les modifications de l'admin.
    public function reset(): void
    {
        $this->catalog = null;
    }

    /** @return array{optgroups: list<array{value: string, label: string}>, suggestions: list<array{value: string, text: string, optgroup: string, moon: ?string}>} */
    private function catalog(): array
    {
        if (null === $this->catalog) {
            $systems = array_filter($this->locationRepository->findAllSorted(), static fn (Location $location): bool => $location->isSystem());
            $suggestions = [];
            foreach ($systems as $system) {
                array_push($suggestions, ...self::leaves($system, []));
            }
            $groups = array_values(array_unique(array_column($suggestions, 'optgroup')));
            $this->catalog = [
                'optgroups' => array_map(static fn (string $group): array => ['value' => $group, 'label' => $group], $groups),
                'suggestions' => $suggestions,
            ];
        }

        return $this->catalog;
    }

    /**
     * @param list<string> $ancestors
     *
     * @return list<array{value: string, text: string, optgroup: string, moon: ?string}>
     */
    private static function leaves(Location $location, array $ancestors): array
    {
        $children = $location->children();

        if ([] === $children) {
            return [] === $ancestors ? [] : [self::suggestion($location->name(), $ancestors)];
        }

        $leaves = [];
        foreach ($children as $child) {
            array_push($leaves, ...self::leaves($child, [...$ancestors, $location->name()]));
        }

        return $leaves;
    }

    /**
     * @param non-empty-list<string> $ancestors
     *
     * @return array{value: string, text: string, optgroup: string, moon: ?string}
     */
    private static function suggestion(string $name, array $ancestors): array
    {
        $detail = \array_slice($ancestors, self::GROUP_DEPTH);

        return [
            'value' => $name,
            'text' => $name,
            'optgroup' => implode(self::SEPARATOR, \array_slice($ancestors, 0, self::GROUP_DEPTH)),
            'moon' => [] === $detail ? null : implode(self::SEPARATOR, $detail),
        ];
    }
}
