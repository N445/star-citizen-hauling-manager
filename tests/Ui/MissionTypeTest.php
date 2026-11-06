<?php

declare(strict_types=1);

namespace App\Tests\Ui;

use App\Domain\ShipCatalog;
use App\Twig\Components\HaulingPlanner;
use App\Twig\Components\Plan\PlanFactory;
use App\Twig\Components\Plan\PlannerState;
use App\Twig\Components\RouteStepper;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class MissionTypeTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function missions(): iterable
    {
        yield 'direct' => [['type' => 'direct', 'pickup' => 'area18', 'dropoff' => 'lorville', 'lots' => [
            ['resource' => 'Gold', 'scu' => 20],
            ['resource' => 'Laranite', 'scu' => 10],
        ]], 'Direct'];

        yield 'multiple drops' => [['type' => 'multipleDropOff', 'pickup' => 'area18', 'lots' => [
            ['resource' => 'Gold', 'scu' => 8, 'dropoff' => 'lorville'],
            ['resource' => 'Gold', 'scu' => 8, 'dropoff' => 'new-babbage'],
        ]], 'Plusieurs drops'];

        yield 'multiple pickups' => [['type' => 'multiplePickUp', 'dropoff' => 'lorville', 'lots' => [
            ['resource' => 'Gold', 'scu' => 8, 'pickup' => 'area18'],
            ['resource' => 'Gold', 'scu' => 8, 'pickup' => 'new-babbage'],
        ]], 'Plusieurs pickups'];

        yield 'free shopping with a destination' => [['type' => 'freeShopping', 'dropoff' => 'lorville', 'lots' => [
            ['resource' => 'Medical Supplies', 'scu' => 12, 'pickup' => 'area18'],
            ['resource' => 'Scrap', 'scu' => 4, 'pickup' => 'new-babbage'],
        ]], 'Achat libre'];

        yield 'free shopping without destination' => [['type' => 'freeShopping', 'dropoff' => '', 'lots' => [
            ['resource' => 'Medical Supplies', 'scu' => 12, 'pickup' => 'area18'],
        ]], 'Achat libre'];
    }

    /** @param array<string, mixed> $mission */
    #[DataProvider('missions')]
    public function testEachTypeBuildsAPlanLabelledInTheStepper(array $mission, string $label): void
    {
        $client = self::createClient();
        $input = PlannerState::planInput(PlannerState::normalize(['missions' => [$mission + ['label' => 'M']]]));
        self::assertNotNull(self::getContainer()->get(PlanFactory::class)->build($input));

        $html = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client)->render()->toString();

        self::assertStringContainsString('data-testid="mission-type-label">'.$label.'<', $html);
    }

    public function testFreeShoppingWithoutDestinationKeepsCratesAboard(): void
    {
        [$mission] = iterator_to_array(self::missions())['free shopping without destination'];
        $input = PlannerState::planInput(PlannerState::normalize(['missions' => [$mission]]));

        $client = self::createClient();
        $html = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client)->render()->toString();

        self::assertStringContainsString('reste à bord', $html);
    }

    public function testMaxContainerSizeLimitsTheCrates(): void
    {
        $mission = ['type' => 'direct', 'pickup' => 'area18', 'dropoff' => 'lorville', 'maxSize' => 8, 'lots' => [['resource' => 'Gold', 'scu' => 48]]];
        $input = PlannerState::planInput(PlannerState::normalize(['missions' => [$mission]]));

        $plan = self::getContainer()->get(PlanFactory::class)->build($input);

        foreach ($plan->placement->crates as $placed) {
            self::assertLessThanOrEqual(8, $placed->crate->sizeScu);
        }
    }

    public function testRenderedFormMatchesTheType(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, [], $client);
        $forms = [];
        foreach (['direct', 'multipleDropOff', 'multiplePickUp', 'freeShopping'] as $type) {
            $missions = $planner->component()->missions;
            $missions[0]['type'] = $type;
            $missions[0]['appliedType'] = $type;
            $planner->set('missions', $missions);
            $forms[$type] = $planner->render()->toString();
        }

        // Direct: shared pickup and drop only; multiple drops: a drop per lot; free shopping: optional destination.
        self::assertStringContainsString('missions[0][dropoff]', $forms['direct']);
        self::assertStringNotContainsString('[lots][0][dropoff]', $forms['direct']);
        self::assertStringContainsString('[lots][0][dropoff]', $forms['multipleDropOff']);
        self::assertStringContainsString('[lots][0][pickup]', $forms['multiplePickUp']);
        self::assertStringContainsString("Lieu d'achat", $forms['freeShopping']);
        self::assertStringContainsString('facultatif', $forms['freeShopping']);
    }

    public function testChangingTypeKeepsCompatibleValues(): void
    {
        $mission = PlannerState::normalize(['missions' => [[
            'type' => 'direct', 'pickup' => 'orison', 'dropoff' => 'lorville',
            'lots' => [['resource' => 'Gold', 'scu' => 8, 'pickup' => 'area18', 'dropoff' => 'area18']],
        ]]])['missions'][0];

        // Direct -> multiple drops: each lot gets the former shared drop, the shared pickup stays.
        $mission['type'] = 'multipleDropOff';
        $adapted = PlannerState::adaptType($mission);
        self::assertSame('lorville', $adapted['lots'][0]['dropoff']);
        self::assertSame('orison', $adapted['pickup']);
        self::assertSame('multipleDropOff', $adapted['appliedType']);

        // multiple drops -> multiple pickups: pickups come from the shared pickup, the drop from the first lot.
        $adapted['type'] = 'multiplePickUp';
        $back = PlannerState::adaptType($adapted);
        self::assertSame('orison', $back['lots'][0]['pickup']);
        self::assertSame('lorville', $back['dropoff']);

        // anything -> free shopping then back to direct never leaves an empty destination.
        $back['type'] = 'freeShopping';
        $shopping = PlannerState::adaptType($back);
        $shopping['dropoff'] = '';
        $shopping['type'] = 'direct';
        $shopping['appliedType'] = 'freeShopping';
        self::assertSame('lorville', PlannerState::adaptType($shopping)['dropoff']);
    }

    public function testInconsistentMissionShowsTheFrenchDomainError(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, ['ship' => ShipCatalog::DEFAULT], $client);
        $missions = PlannerState::normalize(['missions' => [[
            'type' => 'direct', 'pickup' => 'area18', 'dropoff' => 'area18', 'lots' => [['resource' => 'Gold', 'scu' => 8]],
        ]]])['missions'];
        $planner->set('missions', $missions);
        $html = $planner->call('calculate')->render()->toString();

        self::assertStringContainsString('data-testid="error"', $html);
    }

    public function testStateKeepsTypeAndMaxSizeThroughRestore(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, [], $client);
        $state = json_encode(['missions' => [['type' => 'multiplePickUp', 'maxSize' => 20, 'dropoff' => 'lorville', 'lots' => [['resource' => 'Gold', 'scu' => 8, 'pickup' => 'area18']]]]], JSON_THROW_ON_ERROR);
        $planner->call('restore', ['state' => $state]);

        self::assertSame('multiplePickUp', $planner->component()->missions[0]['type']);
        self::assertSame(16, $planner->component()->missions[0]['maxSize']);

        $planner = $this->createLiveComponent(HaulingPlanner::class, [], $client);
        $planner->call('restore', ['state' => '{"missions":[{"type":"bogus","maxSize":999}]}']);
        self::assertSame('direct', $planner->component()->missions[0]['type']);
        self::assertSame(32, $planner->component()->missions[0]['maxSize']);
    }
}
