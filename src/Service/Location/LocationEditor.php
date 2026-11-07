<?php

declare(strict_types=1);

namespace App\Service\Location;

use App\Entity\Location;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Écritures sur l'arbre des lieux : nom unique parmi les frères (sans tenir compte de la casse),
 * pas de cycle, la suppression emporte les descendants.
 */
final readonly class LocationEditor
{
    private const int NAME_LENGTH = 120;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LocationRepository $locations,
    ) {
    }

    public function add(?Location $parent, string $name): Location
    {
        return $this->addMany($parent, $name)[0];
    }

    /**
     * Un lieu par ligne ; tout ou rien : à la moindre erreur rien n'est enregistré.
     *
     * @return non-empty-list<Location>
     */
    public function addMany(?Location $parent, string $lines): array
    {
        $names = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R/', $lines) ?: []),
            static fn (string $name): bool => '' !== $name,
        ));
        if ([] === $names) {
            throw new LocationException('location.error.empty');
        }

        $taken = $this->takenNames($parent);
        $created = [];
        foreach ($names as $name) {
            $this->assertValidName($name);
            $key = self::key($name);
            if (isset($taken[$key])) {
                throw new LocationException('location.error.duplicate', ['name' => $name]);
            }
            $taken[$key] = true;
            $created[] = $name;
        }

        $locations = array_map(fn (string $name): Location => new Location($name, $parent), $created);
        foreach ($locations as $location) {
            $this->entityManager->persist($location);
        }
        $this->entityManager->flush();

        return $locations;
    }

    public function rename(Location $location, string $name): void
    {
        $name = trim($name);
        $this->assertValidName($name);
        if (self::key($name) !== self::key($location->name())) {
            $this->assertFree($location->parent(), $name, $location);
        }

        $location->rename($name);
        $this->entityManager->flush();
    }

    public function move(Location $location, ?Location $parent): void
    {
        if (null !== $parent && $parent->isSelfOrDescendantOf($location)) {
            throw new LocationException('location.error.cycle', ['name' => $location->name(), 'parent' => (string) $parent]);
        }
        if ($parent === $location->parent()) {
            return;
        }
        $this->assertFree($parent, $location->name(), $location);

        $location->moveTo($parent);
        $this->entityManager->flush();
    }

    /**
     * @return int le nombre de descendants supprimés avec le lieu
     */
    public function delete(Location $location): int
    {
        $descendants = $location->descendants();
        foreach (array_reverse($descendants) as $descendant) {
            $this->entityManager->remove($descendant);
        }
        $this->entityManager->remove($location);
        $this->entityManager->flush();

        return \count($descendants);
    }

    private function assertValidName(string $name): void
    {
        if ('' === $name) {
            throw new LocationException('location.error.empty');
        }
        if (mb_strlen($name) > self::NAME_LENGTH) {
            throw new LocationException('location.error.too_long', ['name' => $name, 'max' => self::NAME_LENGTH]);
        }
    }

    private function assertFree(?Location $parent, string $name, ?Location $except = null): void
    {
        $key = self::key($name);
        foreach ($this->siblings($parent) as $sibling) {
            if ($sibling !== $except && self::key($sibling->name()) === $key) {
                throw new LocationException('location.error.duplicate', ['name' => $name]);
            }
        }
    }

    /** @return array<string, true> */
    private function takenNames(?Location $parent): array
    {
        $taken = [];
        foreach ($this->siblings($parent) as $sibling) {
            $taken[self::key($sibling->name())] = true;
        }

        return $taken;
    }

    /** @return list<Location> */
    private function siblings(?Location $parent): array
    {
        return null === $parent ? $this->locations->findSystems() : $parent->children();
    }

    private static function key(string $name): string
    {
        return mb_strtolower($name);
    }
}
