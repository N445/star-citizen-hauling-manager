<?php

declare(strict_types=1);

namespace App\Tests\Ui;

use App\Domain\ShipCatalog;
use App\Twig\Components\HaulingPlanner;
use App\Twig\Components\Plan\PlanFactory;
use App\Twig\Components\Plan\PlannerState;
use App\Twig\Components\RouteStepper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class PlacementTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testValidMoveBecomesAPinAndMovesTheCrate(): void
    {
        $client = self::createClient();
        $input = $this->input();
        $placement = self::getContainer()->get(PlanFactory::class)->build($input)->placement;
        $crate = $placement->crates[0];
        $target = $this->freeSpot($input, $crate->crate->id);

        $stepper = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client);
        $stepper->call('moveCrate', ['crate' => $crate->crate->id, ...$target]);

        self::assertNull($stepper->component()->notice);
        self::assertSame(['x' => $target['x'], 'y' => $target['y'], 'z' => $target['z'], 'o' => $target['orientation']], $stepper->component()->input['pins'][$crate->crate->id]);

        $moved = $stepper->component()->getPlan()->placement->forCrate($crate->crate->id);
        self::assertSame([$target['x'], $target['y'], $target['z']], [$moved->x, $moved->y, $moved->z]);
    }

    public function testRefusedMoveShowsTheFrenchReasonAndKeepsPins(): void
    {
        $client = self::createClient();
        $input = $this->input();
        $crateId = self::getContainer()->get(PlanFactory::class)->build($input)->placement->crates[0]->crate->id;

        $stepper = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client);
        $html = $stepper->call('moveCrate', ['crate' => $crateId, 'x' => 999, 'y' => 999, 'z' => 0, 'orientation' => 0])->render()->toString();

        self::assertNotNull($stepper->component()->notice);
        self::assertSame([], $stepper->component()->input['pins']);
        self::assertStringContainsString('data-testid="notice"', $html);
    }

    public function testPinSurvivesRecalculationAndDoorChange(): void
    {
        $client = self::createClient();
        $input = $this->input();
        $crateId = self::getContainer()->get(PlanFactory::class)->build($input)->placement->crates[0]->crate->id;
        $target = $this->freeSpot($input, $crateId);

        $stepper = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client);
        $stepper->call('moveCrate', ['crate' => $crateId, ...$target]);
        $pins = $stepper->component()->input['pins'];

        $stepper->call('changeDoor', ['door' => 'front']);
        self::assertSame('front', $stepper->component()->input['door']);
        self::assertSame($pins, $stepper->component()->input['pins']);

        // The planner learns about the pin through the event...
        $listener = $this->createLiveComponent(HaulingPlanner::class, [], $client);
        $listener->emit('pinsChanged', ['pins' => $pins]);
        self::assertSame($pins, $listener->component()->pins);

        // ...and keeps it when the tour is recomputed.
        $planner = $this->createLiveComponent(HaulingPlanner::class, ['missions' => PlannerState::normalize($input)['missions'], 'pins' => $pins], $client);
        $planner->call('calculate');
        self::assertNull($planner->component()->error);
        self::assertSame($pins, $planner->component()->pins);
    }

    public function testInvalidPinIsIgnoredAndReported(): void
    {
        $client = self::createClient();
        $input = $this->input();
        $input['pins'] = ['no-such-crate' => ['x' => 0, 'y' => 0, 'z' => 0, 'o' => 0]];
        $html = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client)->render()->toString();

        self::assertStringContainsString('data-testid="ignored-pins"', $html);
        self::assertStringContainsString('no-such-crate', $html);
    }

    public function testUnpinAndClearPins(): void
    {
        $client = self::createClient();
        $input = $this->input();
        $crateId = self::getContainer()->get(PlanFactory::class)->build($input)->placement->crates[0]->crate->id;
        $target = $this->freeSpot($input, $crateId);

        $stepper = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client);
        $stepper->call('moveCrate', ['crate' => $crateId, ...$target]);
        self::assertCount(1, $stepper->component()->input['pins']);
        $stepper->call('unpin', ['crate' => $crateId]);
        self::assertSame([], $stepper->component()->input['pins']);

        $stepper->call('moveCrate', ['crate' => $crateId, ...$target]);
        $stepper->call('clearPins');
        self::assertSame([], $stepper->component()->input['pins']);
    }

    public function testDoorSideIsPersistedPerShip(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, [], $client);
        $planner->emit('doorChanged', ['ship' => ShipCatalog::DEFAULT, 'door' => 'left']);
        $planner->emit('doorChanged', ['ship' => 'unknown-ship', 'door' => 'left']);

        self::assertSame([ShipCatalog::DEFAULT => 'left'], $planner->component()->doors);
    }

    public function testPositionsEndpointReturnsGroupedOrientations(): void
    {
        $client = self::createClient();
        $input = $this->input();
        $crateId = self::getContainer()->get(PlanFactory::class)->build($input)->placement->crates[0]->crate->id;

        $client->jsonRequest('POST', '/placement/positions', ['input' => $input, 'crate' => $crateId]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($crateId, $data['crate']);
        self::assertNotEmpty($data['orientations'][0]['cells']);
        self::assertGreaterThanOrEqual(1, $data['count']);

        $client->jsonRequest('POST', '/placement/check', ['input' => $input, 'crate' => $crateId, 'x' => 999, 'y' => 0, 'z' => 0, 'o' => 0]);
        $check = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsString($check['reason']);

        $client->jsonRequest('POST', '/placement/positions', ['input' => $input, 'crate' => 'nope']);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{x: int, y: int, z: int, orientation: int}
     */
    private function freeSpot(array $input, string $crateId): array
    {
        $placement = self::getContainer()->get(PlanFactory::class)->build($input)->placement;
        $current = $placement->forCrate($crateId);
        foreach ($placement->validPositions($crateId) as $position) {
            if ([$position['x'], $position['y'], $position['z'], $position['orientation']] !== [$current->x, $current->y, $current->z, $current->orientation]) {
                return $position;
            }
        }

        self::fail('No alternative position.');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'ship' => 'drake-caterpillar',
            'start' => 'area18',
            'door' => 'rear',
            'pins' => [],
            'missions' => [
                ['uid' => 'aaaaaaaa', 'label' => 'Alpha', 'reward' => 0, 'lots' => [
                    ['uid' => 'bbbbbbbb', 'resource' => 'Laranite', 'scu' => 20, 'pickup' => 'area18', 'dropoff' => 'lorville'],
                ]],
            ],
        ];
    }
}
