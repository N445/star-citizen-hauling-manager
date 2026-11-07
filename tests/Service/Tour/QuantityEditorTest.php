<?php

declare(strict_types=1);

namespace App\Tests\Service\Tour;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\Tour;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Service\Tour\QuantityEditor;
use App\Service\Tour\QuantityException;
use App\Tests\Philosophy\Support\IdSequence;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuantityEditorTest extends TestCase
{
    private QuantityEditor $editor;

    private IdSequence $missionIds;

    private IdSequence $deliveryIds;

    protected function setUp(): void
    {
        $this->editor = new QuantityEditor($this->createStub(EntityManagerInterface::class));
        $this->missionIds = new IdSequence();
        $this->deliveryIds = new IdSequence();
    }

    /**
     * @param list<string>             $pickups
     * @param list<array{string, int}> $drops
     */
    private function mission(Tour $tour, MissionType $type, array $pickups, array $drops): Mission
    {
        $deliveries = array_map(function (array $drop): Delivery {
            $delivery = new Delivery($drop[0], 'Laranite', $drop[1]);
            $this->deliveryIds->assign($delivery);

            return $delivery;
        }, $drops);
        $mission = new Mission(null, 1000, $type, $pickups, $deliveries);
        $this->missionIds->assign($mission);
        $tour->addMission($mission);

        return $mission;
    }

    public function testDeliveryQuantityReplacesTheEstimate(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Area 18', 11]]);
        $delivery = $mission->deliveries()->first();

        $this->editor->setDelivery($tour, $delivery->id(), 2);

        self::assertSame(2, $delivery->effectiveQuantity());
        self::assertSame(11, $delivery->quantity());
        self::assertSame(2, $mission->effectiveQuantity());
        self::assertSame(11, $mission->totalQuantity());
    }

    public function testBackToTheEstimateForgetsTheAdjustment(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $delivery = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Area 18', 11]])->deliveries()->first();

        $this->editor->setDelivery($tour, $delivery->id(), 4);
        $this->editor->setDelivery($tour, $delivery->id(), 11);

        self::assertFalse($delivery->hasActualQuantity());
    }

    public function testDirectMissionPickupEditsItsOnlyDelivery(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Area 18', 11]]);

        $this->editor->setPickup($tour, $mission->id(), 'Lorville', 7);

        self::assertSame(7, $mission->effectiveQuantity());
    }

    public function testSplitPickupsBecomeTheDeliveredQuantityOnceAllAreEntered(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::MultiplePickUp, ['A', 'B', 'C'], [['Averus', 11]]);

        $this->editor->setPickup($tour, $mission->id(), 'A', 2);
        $this->editor->setPickup($tour, $mission->id(), 'B', 5);
        self::assertSame(11, $mission->effectiveQuantity(), 'while a location is missing, the estimate stays');

        $this->editor->setPickup($tour, $mission->id(), 'C', 3);
        self::assertSame(10, $mission->effectiveQuantity());

        $this->editor->setPickup($tour, $mission->id(), 'C', null);
        self::assertSame(11, $mission->effectiveQuantity());
        self::assertNull($mission->pickupQuantity('C'));
    }

    public function testSeveralDropOffsKeepTheirPickupQuantityAsInformation(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::MultipleDropOff, ['Lorville'], [['A', 4], ['B', 6]]);

        $this->editor->setPickup($tour, $mission->id(), 'Lorville', 8);

        self::assertSame(8, $mission->pickupQuantity('Lorville'));
        self::assertSame(10, $mission->effectiveQuantity());
    }

    public function testChangingTheQuantityClearsTheMissionCrates(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Area 18', 11]]);
        $mission->freezeCargo([['delivery' => 1, 'size' => 1, 'x' => 0.0, 'y' => 0.0, 'z' => 0.0, 'width' => 1, 'height' => 1, 'length' => 1]]);

        $this->editor->setDelivery($tour, $mission->deliveries()->first()->id(), 5);

        self::assertNull($mission->frozenCargo());
    }

    public function testUnchangedQuantityKeepsTheCrates(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Area 18', 11]]);
        $mission->freezeCargo([['delivery' => 1, 'size' => 1, 'x' => 0.0, 'y' => 0.0, 'z' => 0.0, 'width' => 1, 'height' => 1, 'length' => 1]]);

        $this->editor->setDelivery($tour, $mission->deliveries()->first()->id(), 11);

        self::assertNotNull($mission->frozenCargo());
    }

    public function testEditingTheMissionResetsRealQuantities(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::MultiplePickUp, ['A', 'B'], [['Averus', 11]]);
        $this->editor->setPickup($tour, $mission->id(), 'A', 2);
        $this->editor->setPickup($tour, $mission->id(), 'B', 3);

        $mission->update(null, 1000, MissionType::MultiplePickUp, ['A', 'B'], [new Delivery('Averus', 'Laranite', 11)]);

        self::assertSame([], $mission->pickupQuantities());
        self::assertSame(11, $mission->effectiveQuantity());
    }

    #[DataProvider('invalidQuantities')]
    public function testRefusesQuantitiesOutOfRangeOrAboveTheShipCapacity(int $quantity): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $delivery = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Area 18', 11]])->deliveries()->first();

        $this->expectException(QuantityException::class);
        $this->editor->setDelivery($tour, $delivery->id(), $quantity);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidQuantities(): iterable
    {
        yield 'negative' => [-1];
        yield 'above the ceiling' => [QuantityEditor::MAXIMUM + 1];
        yield 'more than the capacity' => [49];
    }

    public function testRealQuantityFreesCapacityInTheRoute(): void
    {
        $tour = new Tour('T', 'Cube', 20, null, 'Lorville');
        $first = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Area 18', 20]]);
        $second = $this->mission($tour, MissionType::Direct, ['Lorville'], [['Orison', 20]]);
        $planner = new RoutePlanner();
        $before = $planner->plan($tour, RouteMode::FewestStops);

        $this->editor->setDelivery($tour, $first->deliveries()->first()->id(), 5);
        $this->editor->setDelivery($tour, $second->deliveries()->first()->id(), 5);
        $after = $planner->plan($tour, RouteMode::FewestStops);

        self::assertLessThan($before->moveCount(), $after->moveCount());
    }

    public function testUnknownPickupLocationIsRejected(): void
    {
        $tour = new Tour('T', 'Cube', 48, null, 'Lorville');
        $mission = $this->mission($tour, MissionType::MultipleDropOff, ['Lorville'], [['A', 4], ['B', 6]]);

        $this->expectException(\OutOfBoundsException::class);
        $this->editor->setPickup($tour, $mission->id(), 'Nulle part', 3);
    }
}
