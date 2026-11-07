<?php

declare(strict_types=1);

namespace App\Tests\Service\Cargo;

use App\Entity\Delivery;
use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\Ship;
use App\Entity\Tour;
use App\Service\Cargo\CargoEditor;
use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\CargoPlanner;
use App\Service\Cargo\PlacedCrate;
use App\Service\Cargo\PlacementException;
use App\Service\Route\Route;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\IdSequence;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CargoEditorTest extends TestCase
{
    private CargoEditor $editor;

    protected function setUp(): void
    {
        $this->editor = new CargoEditor($this->createStub(EntityManagerInterface::class));
    }

    /**
     * @param list<int> $quantities une mission directe Lorville vers Area 18 par quantité
     */
    private function tour(array $quantities): Tour
    {
        $ship = new Ship('Test', 'Cube', 48, [['x' => 0, 'z' => 0, 'grids' => [['x' => 0, 'y' => 0, 'z' => 0, 'width' => 4, 'height' => 2, 'length' => 6]]]]);
        $ship->changeDoors([DoorSide::Rear]);
        $tour = new Tour('Test', 'Cube', 48, $ship, 'Lorville');
        $missionIds = new IdSequence();
        $deliveryIds = new IdSequence();

        foreach ($quantities as $quantity) {
            $delivery = new Delivery('Area 18', 'Laranite', $quantity);
            $deliveryIds->assign($delivery);
            $mission = new Mission(null, 1000, MissionType::Direct, ['Lorville'], [$delivery]);
            $missionIds->assign($mission);
            $tour->addMission($mission);
        }

        return $tour;
    }

    private function route(Tour $tour): Route
    {
        return (new RoutePlanner())->plan($tour, RouteMode::FewestStops);
    }

    private function plan(Tour $tour): CargoPlan
    {
        return (new CargoPlanner())->plan($tour, $this->route($tour));
    }

    private function crate(CargoPlan $plan, int $missionId, int $index = 0): PlacedCrate
    {
        return $plan->crateAt($missionId, $index) ?? self::fail('caisse introuvable');
    }

    /**
     * @return array{float, float, float}
     */
    private function origin(PlacedCrate $crate): array
    {
        return [$crate->spot->x(), $crate->spot->y(), $crate->spot->z()];
    }

    public function testMovedCrateStaysWhereTheUserPutItAfterEveryRecalculation(): void
    {
        $tour = $this->tour([4]);
        $mission = $tour->missions()->first();

        $this->editor->move($tour, $this->route($tour), $mission->id(), 0, ['x' => 2.0, 'y' => 0.0, 'z' => 4.0, 'width' => 2, 'length' => 2]);

        self::assertTrue($mission->hasManualCargo());

        $crate = $this->crate($this->plan($tour), $mission->id());
        self::assertSame([2.0, 0.0, 4.0], $this->origin($crate));
        self::assertTrue($crate->manual);
    }

    public function testRotatedCrateKeepsItsNewOrientation(): void
    {
        $tour = $this->tour([2]);
        $mission = $tour->missions()->first();
        $before = $this->crate($this->plan($tour), $mission->id());
        $turned = ['x' => 0.0, 'y' => 0.0, 'z' => 0.0, 'width' => $before->spot->length, 'length' => $before->spot->width];

        $this->editor->move($tour, $this->route($tour), $mission->id(), 0, $turned);

        $after = $this->crate($this->plan($tour), $mission->id());
        self::assertSame([$turned['width'], $turned['length']], [$after->spot->width, $after->spot->length]);
    }

    public function testRefusedMoveChangesNothing(): void
    {
        $tour = $this->tour([4]);
        $mission = $tour->missions()->first();

        try {
            $this->editor->move($tour, $this->route($tour), $mission->id(), 0, ['x' => 3.0, 'y' => 0.0, 'z' => 0.0, 'width' => 2, 'length' => 2]);
            self::fail('Une caisse hors de la grille doit être refusée.');
        } catch (PlacementException $exception) {
            self::assertSame('cargo.edit.error.outside', $exception->getMessage());
        }

        self::assertNull($mission->frozenCargo());
    }

    public function testCrateCannotLandOnAnotherOne(): void
    {
        $tour = $this->tour([8, 8]);
        [$first, $second] = $tour->missions()->getValues();
        $target = $this->crate($this->plan($tour), $second->id())->spot;

        $this->expectExceptionObject(new PlacementException('cargo.edit.error.overlap'));

        $this->editor->move($tour, $this->route($tour), $first->id(), 0, ['x' => $target->x(), 'y' => $target->y(), 'z' => $target->z(), 'width' => 2, 'length' => 2]);
    }

    public function testTwoCratesOfTheSameSizeCanBeSwapped(): void
    {
        $tour = $this->tour([8, 8]);
        [$first, $second] = $tour->missions()->getValues();
        $before = $this->plan($tour);
        $firstOrigin = $this->origin($this->crate($before, $first->id()));
        $secondOrigin = $this->origin($this->crate($before, $second->id()));

        $this->editor->swap($tour, $this->route($tour), $first->id(), 0, $second->id(), 0);

        $after = $this->plan($tour);
        self::assertSame($secondOrigin, $this->origin($this->crate($after, $first->id())));
        self::assertSame($firstOrigin, $this->origin($this->crate($after, $second->id())));
    }

    public function testCratesOfDifferentSizesCannotBeSwapped(): void
    {
        $tour = $this->tour([8, 4]);
        [$first, $second] = $tour->missions()->getValues();

        $this->expectExceptionObject(new PlacementException('cargo.edit.error.swap'));

        $this->editor->swap($tour, $this->route($tour), $first->id(), 0, $second->id(), 0);
    }

    public function testCratesMarkedAsPlacedCannotBeMovedUntilUnmarked(): void
    {
        $tour = $this->tour([4]);
        $mission = $tour->missions()->first();
        $route = $this->route($tour);
        $this->editor->markPlaced($tour, $route, $mission->id(), 0, true);

        self::assertTrue($this->crate($this->plan($tour), $mission->id())->placed);
        self::assertFalse($mission->hasManualCargo());

        try {
            $this->editor->move($tour, $route, $mission->id(), 0, ['x' => 2.0, 'y' => 0.0, 'z' => 4.0, 'width' => 2, 'length' => 2]);
            self::fail('Une caisse posée ne doit pas bouger.');
        } catch (PlacementException $exception) {
            self::assertSame('cargo.edit.error.placed', $exception->getMessage());
        }

        $this->editor->markPlaced($tour, $route, $mission->id(), 0, false);
        $this->editor->move($tour, $route, $mission->id(), 0, ['x' => 2.0, 'y' => 0.0, 'z' => 4.0, 'width' => 2, 'length' => 2]);

        self::assertFalse($this->crate($this->plan($tour), $mission->id())->placed);
    }

    public function testResetGoesBackToTheAutomaticPlacement(): void
    {
        $tour = $this->tour([4]);
        $mission = $tour->missions()->first();
        $automatic = $this->origin($this->crate($this->plan($tour), $mission->id()));

        $this->editor->move($tour, $this->route($tour), $mission->id(), 0, ['x' => 2.0, 'y' => 0.0, 'z' => 4.0, 'width' => 2, 'length' => 2]);
        $this->editor->reset($tour, $this->route($tour));

        self::assertFalse($mission->hasManualCargo());
        self::assertSame($automatic, $this->origin($this->crate($this->plan($tour), $mission->id())));
    }

    public function testResetKeepsAPickedUpMissionWhereItIsInTheHold(): void
    {
        $tour = $this->tour([4]);
        $mission = $tour->missions()->first();
        $route = $this->route($tour);
        $this->editor->move($tour, $route, $mission->id(), 0, ['x' => 2.0, 'y' => 0.0, 'z' => 4.0, 'width' => 2, 'length' => 2]);
        $mission->togglePickupAt('Lorville');

        $this->editor->reset($tour, $this->route($tour), $mission->id());

        self::assertFalse($mission->hasManualCargo());
        self::assertNotNull($mission->frozenCargo());
    }

    public function testManualPlacementSurvivesUntickingThePickup(): void
    {
        $tour = $this->tour([4]);
        $mission = $tour->missions()->first();
        $this->editor->move($tour, $this->route($tour), $mission->id(), 0, ['x' => 2.0, 'y' => 0.0, 'z' => 4.0, 'width' => 2, 'length' => 2]);

        $mission->togglePickupAt('Lorville');
        $mission->togglePickupAt('Lorville');

        self::assertTrue($mission->hasManualCargo());
    }

    public function testEditingAMissionErasesItsManualPlacement(): void
    {
        $tour = $this->tour([4]);
        $mission = $tour->missions()->first();
        $this->editor->move($tour, $this->route($tour), $mission->id(), 0, ['x' => 2.0, 'y' => 0.0, 'z' => 4.0, 'width' => 2, 'length' => 2]);

        $mission->update(null, 1000, MissionType::Direct, ['Lorville'], [new Delivery('Area 18', 'Laranite', 4)]);

        self::assertNull($mission->frozenCargo());
    }
}
