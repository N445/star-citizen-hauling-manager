<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Cargo;

use App\Entity\Delivery;
use App\Entity\Tour;
use App\Service\Cargo\CargoPlanner;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Service\Tour\QuantityEditor;
use App\Service\Tour\QuantityException;
use App\Tests\Philosophy\Support\CargoSafetyInvariants;
use App\Tests\Philosophy\Support\RandomTours;
use App\Tests\Philosophy\Support\RouteInvariants;
use App\Tests\Philosophy\Support\TourBlueprint;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Quantités réelles saisies en route : quelles qu'elles soient (y compris 0 ou plus que l'estimation), la capacité
 * du vaisseau et la sûreté de la soute tiennent.
 */
final class RealQuantityPhilosophyTest extends TestCase
{
    private const int FIRST_SEED = 7001;

    private const int CASE_COUNT = 80;

    /**
     * @return iterable<string, array{TourBlueprint, RouteMode, int}>
     */
    public static function toursWithRealQuantities(): iterable
    {
        foreach (range(self::FIRST_SEED, self::FIRST_SEED + self::CASE_COUNT - 1) as $seed) {
            $blueprint = (new RandomTours($seed))->cargoTour();
            $mode = 0 === $seed % 3 ? RouteMode::MissionFocus : RouteMode::FewestStops;
            yield $blueprint->name().' · '.$mode->label() => [$blueprint, $mode, $seed];
        }
    }

    #[DataProvider('toursWithRealQuantities')]
    public function testCapacityAndHoldStaySafeWithAnyRealQuantities(TourBlueprint $blueprint, RouteMode $mode, int $seed): void
    {
        $tour = $blueprint->tour();
        $this->enterRealQuantities($tour, $seed);
        $route = (new RoutePlanner())->plan($tour, $mode);
        $case = $blueprint->name().' · quantités réelles';

        (new RouteInvariants($tour, $route, $case))->assertCommonPhilosophy();
        (new CargoSafetyInvariants($tour, $route, (new CargoPlanner())->plan($tour, $route), $case))->assertAll();
    }

    private function enterRealQuantities(Tour $tour, int $seed): void
    {
        $random = new Randomizer(new Mt19937($seed));
        $editor = new QuantityEditor($this->createStub(EntityManagerInterface::class));

        foreach ($tour->missions() as $mission) {
            foreach ($mission->deliveries() as $delivery) {
                /** @var Delivery $delivery */
                try {
                    $editor->setDelivery($tour, $delivery->id(), $random->getInt(0, max(1, 2 * $delivery->quantity())));
                } catch (QuantityException) {
                    // refusée parce que la mission dépasserait la capacité : l'estimation reste
                }
            }
        }
    }
}
