<?php

declare(strict_types=1);

namespace App\Service\Example;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\Tour;
use App\Entity\User;
use App\Repository\ShipRepository;
use App\Repository\TourRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ExampleTourLoader
{
    public function __construct(
        private ShipRepository $shipRepository,
        private TourRepository $tourRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param int|null $number numéro de l'exemple à recréer (1 = le premier), tous si null
     */
    public function load(?int $number, ?User $owner = null): int
    {
        $definitions = null === $number ? ExampleTours::all() : \array_slice(ExampleTours::all(), $number - 1, 1);

        foreach ($definitions as $definition) {
            $this->replace($definition, $owner);
        }

        $this->entityManager->flush();

        return \count($definitions);
    }

    /**
     * @param array{name: string, ship: string, start: string, missions: list<array<string, mixed>>} $definition
     */
    private function replace(array $definition, ?User $owner): void
    {
        foreach ($this->tourRepository->findBy(['name' => $definition['name'], 'owner' => $owner]) as $existing) {
            $this->entityManager->remove($existing);
        }

        $this->entityManager->persist($this->tourOf($definition, $owner));
    }

    /**
     * @param array{name: string, ship: string, start: string, missions: list<array<string, mixed>>} $definition
     */
    private function tourOf(array $definition, ?User $owner): Tour
    {
        $ship = $this->shipRepository->findOneBy(['slug' => $definition['ship']])
            ?? throw new \RuntimeException(sprintf('Vaisseau « %s » absent : lance d\'abord app:ships:import.', $definition['ship']));
        $tour = new Tour($definition['name'], $ship->name(), $ship->capacity(), $ship, $definition['start']);

        if (null !== $owner) {
            $tour->assignTo($owner);
        }

        foreach ($definition['missions'] as $mission) {
            $tour->addMission($this->missionOf($mission));
        }

        if (isset($definition['stopOrder'])) {
            $tour->reorderStops($definition['stopOrder']);
        }

        return $tour;
    }

    /**
     * @param array{name: string, reward: int, type: string, pickups: list<string>, deliveries: list<array{string, string, int}>} $mission
     */
    private function missionOf(array $mission): Mission
    {
        $deliveries = array_map(static fn (array $delivery): Delivery => new Delivery(...$delivery), $mission['deliveries']);

        return new Mission($mission['name'], $mission['reward'], MissionType::from($mission['type']), $mission['pickups'], $deliveries);
    }
}
