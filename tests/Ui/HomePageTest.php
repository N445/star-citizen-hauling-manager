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

final class HomePageTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testHomePageRenders(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Hauling');
        self::assertCount(1, $crawler->filter('[data-testid="mission"]'));
        self::assertSelectorTextContains('button.primary', 'Calculer le tour');
        self::assertSelectorExists('[data-testid="reset"]');
        self::assertStringContainsString('drake-ironclad', $crawler->filter('#sch-catalog')->text());
    }

    public function testPlannerSelectsDoNotEmbedFullCatalogs(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        $html = (string) $client->getResponse()->getContent();
        $planner = substr($html, (int) strpos($html, 'data-live-name-value="HaulingPlanner"'));
        $planner = substr($planner, 0, (int) strpos($planner, '</main>'));
        self::assertLessThan(20, substr_count($planner, '<option'), 'Options are provided by the static catalog, not re-rendered.');
    }

    public function testPlanBuildsFromInput(): void
    {
        $plan = self::getContainer()->get(PlanFactory::class)->build($this->input());

        self::assertNotEmpty($plan->route->stops);
        self::assertSame('Alpha', $plan->missions['m1']['label']);
    }

    public function testCalculateRendersStepperAndGrid(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, [], $client);
        $planner->call('calculate');
        self::assertNull($planner->component()->error);
        self::assertTrue($planner->component()->calculated);

        $stepper = $this->createLiveComponent(RouteStepper::class, ['input' => $this->planInput()], $client);
        $html = $stepper->render()->toString();

        self::assertStringContainsString('Arrêt 1 /', $html);
        self::assertStringContainsString('class="crate pickup"', $html);
        self::assertStringContainsString('class="cargo-block"', $html);
        self::assertStringContainsString('data-testid="mission-actions"', $html);
    }

    public function testBlockRenderingStaysSmallOnHugeShips(): void
    {
        $client = self::createClient();
        $input = $this->planInput('misc-hull-c');
        $html = $this->createLiveComponent(RouteStepper::class, ['input' => $input], $client)->render()->toString();

        self::assertLessThan(400, substr_count($html, '<div'), 'One element per block and per crate, never per empty cell.');
    }

    public function testEmptyStepperShowsPlaceholder(): void
    {
        $html = $this->createLiveComponent(RouteStepper::class, [], self::createClient())->render()->toString();

        self::assertStringContainsString('Calculer le tour', $html);
    }

    public function testDomainErrorIsShownInFrench(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, ['ship' => 'drake-cutlass-black'], $client);
        $planner->set('missions', PlannerState::normalize(['missions' => [['uid' => 'abcdef01', 'label' => '', 'reward' => 0, 'type' => 'direct', 'pickup' => 'area18', 'dropoff' => 'area18', 'lots' => [
            ['uid' => 'abcdef02', 'resource' => 'Gold', 'scu' => 10],
        ]]]])['missions']);
        $html = $planner->call('calculate')->render()->toString();

        self::assertStringContainsString('data-testid="error"', $html);
    }

    public function testRestoreToleratesGarbageAndUnknownShips(): void
    {
        $client = self::createClient();

        foreach (['not json', '{"ship":"old-slug","start":"nowhere","missions":"x"}', '[]', 'null'] as $state) {
            $planner = $this->createLiveComponent(HaulingPlanner::class, [], $client);
            $html = $planner->call('restore', ['state' => $state])->render()->toString();

            self::assertSame(ShipCatalog::DEFAULT, $planner->component()->ship);
            self::assertSame(PlannerState::DEFAULT_START, $planner->component()->start);
            self::assertCount(1, $planner->component()->missions);
            self::assertStringContainsString('data-testid="mission"', $html);
        }
    }

    public function testRestoreKeepsValidStateAndRecalculates(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, [], $client);
        $state = json_encode([...$this->planInput(), 'calculated' => true], JSON_THROW_ON_ERROR);
        $planner->call('restore', ['state' => $state]);

        self::assertSame('drake-caterpillar', $planner->component()->ship);
        self::assertSame('Alpha', $planner->component()->missions[0]['label']);
        self::assertTrue($planner->component()->calculated);
    }

    public function testResetRestoresDefaults(): void
    {
        $client = self::createClient();
        $planner = $this->createLiveComponent(HaulingPlanner::class, ['ship' => 'misc-hull-c'], $client);
        $planner->call('reset');

        self::assertSame(ShipCatalog::DEFAULT, $planner->component()->ship);
        self::assertFalse($planner->component()->calculated);
    }

    public function testEmptyInputIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::getContainer()->get(PlanFactory::class)->build(['ship' => ShipCatalog::DEFAULT, 'start' => 'area18', 'missions' => []]);
    }

    /** @return array{ship: string, start: string, missions: list<array<string, mixed>>} */
    private function planInput(string $ship = ShipCatalog::DEFAULT): array
    {
        return [...$this->input(), 'ship' => $ship];
    }

    /** @return array{ship: string, start: string, missions: list<array<string, mixed>>} */
    private function input(): array
    {
        return [
            'ship' => ShipCatalog::DEFAULT,
            'start' => 'area18',
            'missions' => [
                ['uid' => 'aaaaaaaa', 'label' => 'Alpha', 'reward' => 1000, 'lots' => [
                    ['uid' => 'bbbbbbbb', 'resource' => 'Laranite', 'scu' => 20, 'pickup' => 'area18', 'dropoff' => 'lorville'],
                ]],
            ],
        ];
    }
}
