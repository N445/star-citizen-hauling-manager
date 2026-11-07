<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Delivery;
use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\ReportCategory;
use App\Entity\RouteReport;
use App\Entity\Ship;
use App\Entity\Tour;
use App\Entity\User;
use App\Service\Report\ReportReplay;
use App\Service\Report\RouteReporter;
use App\Service\Route\RouteMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RouteReportAdminTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    private function report(): RouteReport
    {
        $ship = new Ship('Test', 'Cube', 48, [['x' => 0, 'z' => 0, 'grids' => [['x' => 0, 'y' => 0, 'z' => 0, 'width' => 4, 'height' => 2, 'length' => 6]]]]);
        $ship->changeDoors([DoorSide::Rear]);
        $tour = new Tour('Hauling', 'Test Cube', 48, $ship, 'Lorville');
        $delivery = new Delivery('Area 18', 'Gold', 11);
        $delivery->adjustQuantity(6);
        $tour->addMission(new Mission('Gold run', 20_000, MissionType::Direct, ['Lorville'], [$delivery]));
        $tour->reorderStops(['Lorville', 'Area 18']);
        $this->entityManager->persist($ship);
        $this->entityManager->persist($tour);
        $this->entityManager->flush();
        $reporter = static::getContainer()->get(RouteReporter::class);

        return $reporter->report($tour, RouteMode::FewestStops, ReportCategory::Cargo, 'Crates overlap in the hold', null, 'guest-token', 'en');
    }

    private function admin(): void
    {
        $user = new User('admin@example.test');
        $user->grantAdmin();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);
    }

    public function testListAndDetailShowTheReportAndItsSnapshot(): void
    {
        $report = $this->report();
        $this->admin();

        $this->client->request('GET', '/admin/route-report');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Crates overlap in the hold');

        $this->client->request('GET', '/admin/route-report/'.$report->id());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Gold run');
        self::assertSelectorTextContains('body', '6 SCU Gold');
        self::assertSelectorTextContains('body', 'Area 18');
    }

    public function testFiltersByStatusAndCategoryAreAccepted(): void
    {
        $this->report();
        $this->admin();

        $this->client->request('GET', '/admin/route-report', ['filters' => ['status' => ['comparison' => '=', 'value' => 'resolved'], 'category' => ['comparison' => '=', 'value' => 'cargo']]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Crates overlap in the hold');

        $this->client->request('GET', '/admin/route-report', ['filters' => ['status' => ['comparison' => '=', 'value' => 'open'], 'category' => ['comparison' => '=', 'value' => 'cargo']]]);
        self::assertSelectorTextContains('body', 'Crates overlap in the hold');
    }

    public function testResolveAndReopenNeedAValidToken(): void
    {
        $report = $this->report();
        $id = $report->id();
        $this->admin();
        $this->client->request('GET', '/admin/route-report/'.$id);
        $token = $this->client->getCrawler()->filter('form[action$="/resolve"] input[name=_token]')->attr('value');

        $this->client->request('POST', sprintf('/admin/reports/%d/resolve', $report->id()), ['_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);
        $report = $this->entityManager->find(RouteReport::class, $id);
        self::assertFalse($report->isResolved());

        $this->client->request('POST', sprintf('/admin/reports/%d/resolve', $report->id()), ['_token' => $token]);
        self::assertResponseRedirects();
        $report = $this->entityManager->find(RouteReport::class, $id);
        self::assertTrue($report->isResolved());
        self::assertNotNull($report->resolvedAt());

        $this->client->request('POST', sprintf('/admin/reports/%d/reopen', $report->id()), ['_token' => $token]);
        $report = $this->entityManager->find(RouteReport::class, $id);
        self::assertFalse($report->isResolved());
    }

    public function testNonAdminsAreKeptOut(): void
    {
        $report = $this->report();
        $user = new User('pilot@example.test');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/route-report');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', sprintf('/admin/reports/%d/resolve', $report->id()), ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousVisitorsAreSentToLogin(): void
    {
        $this->report();

        $this->client->request('GET', '/admin/route-report');
        self::assertResponseRedirects();
    }

    public function testReplayRebuildsTheTourFromTheSnapshot(): void
    {
        $report = $this->report();
        $owner = new User('replayer@example.test');
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        $tour = static::getContainer()->get(ReportReplay::class)->replay($report, $owner);

        self::assertSame('Report #'.$report->id().' · Hauling', $tour->name());
        self::assertSame(48, $tour->capacity());
        self::assertSame('Lorville', $tour->startLocation());
        self::assertSame(['Lorville', 'Area 18'], $tour->stopOrder());
        self::assertTrue($tour->isOwnedBy($owner));
        $delivery = $tour->missions()->first()->deliveries()->first();
        self::assertSame(6, $delivery->effectiveQuantity());
        self::assertSame(11, $delivery->quantity());
        self::assertNotNull($tour->ship());
    }
}
