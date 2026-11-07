<?php

declare(strict_types=1);

namespace App\Tests\Service\Report;

use App\Entity\Delivery;
use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\ReportCategory;
use App\Entity\RouteReport;
use App\Entity\Ship;
use App\Entity\Tour;
use App\Entity\User;
use App\Repository\RouteReportRepository;
use App\Service\Report\ReportException;
use App\Service\Report\RouteReporter;
use App\Service\Report\RouteSnapshot;
use App\Service\Route\RouteMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class RouteReporterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MockClock $clock;

    private RouteReporter $reporter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->clock = new MockClock('2026-11-07 10:00:00');
        $this->reporter = new RouteReporter(
            static::getContainer()->get(RouteSnapshot::class),
            static::getContainer()->get(RouteReportRepository::class),
            $this->entityManager,
            $this->clock,
        );
    }

    private function tour(?User $owner = null): Tour
    {
        $ship = new Ship('Test', 'Cube', 48, [['x' => 0, 'z' => 0, 'grids' => [['x' => 0, 'y' => 0, 'z' => 0, 'width' => 4, 'height' => 2, 'length' => 6]]]]);
        $ship->changeDoors([DoorSide::Rear]);
        $tour = new Tour('Hauling', 'Test Cube', 48, $ship, 'Lorville');
        $tour->addMission(new Mission('Gold run', 20_000, MissionType::Direct, ['Lorville'], [new Delivery('Area 18', 'Gold', 11)]));
        $tour->addMission(new Mission('Split', 30_000, MissionType::MultiplePickUp, ['Orison', 'Seraphim Station'], [new Delivery('Lorville', 'Laranite', 9)]));
        null === $owner || $tour->assignTo($owner);
        $this->entityManager->persist($ship);
        $this->entityManager->persist($tour);
        $this->entityManager->flush();

        return $tour;
    }

    private function user(string $email = 'pilot@example.test'): User
    {
        $user = new User($email);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function send(Tour $tour, ?User $author, ?string $guestToken = null, string $message = 'The route goes back and forth'): RouteReport
    {
        return $this->reporter->report($tour, RouteMode::FewestStops, ReportCategory::Route, $message, $author, $guestToken, 'fr');
    }

    public function testSnapshotHoldsMissionsRouteAndCargo(): void
    {
        $tour = $this->tour();
        $tour->mission($tour->missions()->first()->id())->deliveries()->first()->adjustQuantity(7);
        $tour->reorderStops(['Orison', 'Seraphim Station', 'Lorville', 'Area 18']);

        $snapshot = $this->send($tour, null, 'guest-token')->snapshot();

        self::assertSame(RouteSnapshot::VERSION, $snapshot['version']);
        self::assertSame('fr', $snapshot['locale']);
        self::assertSame('2026-11-07T10:00:00+00:00', $snapshot['capturedAt']);
        self::assertSame(48, $snapshot['tour']['capacity']);
        self::assertSame('test-cube', $snapshot['tour']['ship']['slug']);
        self::assertSame(['rear'], $snapshot['tour']['doors']);
        self::assertSame('fewest_stops', $snapshot['tour']['mode']);
        self::assertSame(['Orison', 'Seraphim Station', 'Lorville', 'Area 18'], $snapshot['tour']['stopOrder']);
        self::assertSame(['Gold run', 'Split'], array_column($snapshot['missions'], 'name'));
        self::assertSame(7, $snapshot['missions'][0]['deliveries'][0]['actualQuantity']);
        self::assertSame(11, $snapshot['missions'][0]['deliveries'][0]['quantity']);
        self::assertSame(['Lorville', 'Orison', 'Seraphim Station', 'Lorville', 'Area 18'], array_column($snapshot['route']['stops'], 'location'));
        self::assertNull($snapshot['route']['error']);
        self::assertSame(16, array_sum(array_column($snapshot['cargo']['crates'], 'size')));
        self::assertArrayHasKey('manual', $snapshot['cargo']['crates'][0]);
        self::assertArrayHasKey('stacked', $snapshot['cargo']['crates'][0]);
    }

    public function testSnapshotStaysTheSameWhenTheTourChangesAfterwards(): void
    {
        $tour = $this->tour();
        $report = $this->send($tour, null, 'guest-token');
        $before = json_encode($report->snapshot(), \JSON_THROW_ON_ERROR);

        $tour->missions()->first()->update('Renamed', 1, MissionType::Direct, ['Orison'], [new Delivery('Area 18', 'Gold', 3)]);
        $tour->addMission(new Mission(null, 5, MissionType::Direct, ['Lorville'], [new Delivery('Orison', 'Gold', 1)]));
        $tour->update('Another name', 'Test Cube', 40, $tour->ship(), null);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(RouteReport::class, $report->id());

        self::assertSame($before, json_encode($reloaded->snapshot(), \JSON_THROW_ON_ERROR));
        self::assertSame('Hauling', $reloaded->snapshot()['tour']['name']);
        self::assertCount(2, $reloaded->snapshot()['missions']);
    }

    public function testSnapshotNeverHoldsTheAuthorEmail(): void
    {
        $author = $this->user('secret.pilot@example.test');
        $report = $this->send($this->tour($author), $author);

        self::assertSame($author, $report->author());
        self::assertStringNotContainsString('secret.pilot', json_encode($report->snapshot(), \JSON_THROW_ON_ERROR));
        self::assertNull($report->guestToken());
    }

    public function testGuestReportKeepsTheTokenOnly(): void
    {
        $report = $this->send($this->tour(), null, 'guest-token');

        self::assertNull($report->author());
        self::assertSame('guest-token', $report->guestToken());
    }

    public function testRouteErrorIsRecordedInTheSnapshotInsteadOfFailing(): void
    {
        $tour = $this->tour();
        $tour->update('Hauling', 'Test Cube', 5, $tour->ship(), 'Lorville');

        $snapshot = $this->send($tour, null, 'guest-token')->snapshot();

        self::assertNotEmpty($snapshot['route']['error']);
        self::assertSame([], $snapshot['route']['stops']);
        self::assertSame([], $snapshot['cargo']['crates']);
    }

    public function testReportSurvivesTheDeletionOfItsTour(): void
    {
        $tour = $this->tour();
        $report = $this->send($tour, null, 'guest-token');
        $id = $report->id();

        $this->entityManager->remove($tour);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(RouteReport::class, $id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->tour());
        self::assertCount(2, $reloaded->snapshot()['missions']);
    }

    public function testEmptyMessageIsRefused(): void
    {
        $this->expectException(ReportException::class);
        $this->send($this->tour(), null, 'guest-token', "  \n ");
    }

    public function testTooLongMessageIsRefused(): void
    {
        $this->expectException(ReportException::class);
        $this->send($this->tour(), null, 'guest-token', str_repeat('a', RouteReporter::MAX_MESSAGE_LENGTH + 1));
    }

    public function testMessageAtTheLimitIsAccepted(): void
    {
        $report = $this->send($this->tour(), null, 'guest-token', str_repeat('é', RouteReporter::MAX_MESSAGE_LENGTH));

        self::assertSame(RouteReporter::MAX_MESSAGE_LENGTH, mb_strlen($report->message()));
    }

    public function testWithoutAnyIdentityNothingIsStored(): void
    {
        $this->expectException(ReportException::class);
        $this->send($this->tour(), null, null);
    }

    public function testSecondReportTooSoonIsRefusedThenAcceptedLater(): void
    {
        $tour = $this->tour();
        $this->send($tour, null, 'guest-token');
        $this->clock->sleep(RouteReporter::MIN_SECONDS_BETWEEN - 1);

        try {
            $this->send($tour, null, 'guest-token');
            self::fail('a second report right after the first one should be refused');
        } catch (ReportException $exception) {
            self::assertSame('report.error.too_soon', $exception->getMessage());
        }

        $this->clock->sleep(1);
        $this->send($tour, null, 'guest-token');
        self::assertCount(2, $this->entityManager->getRepository(RouteReport::class)->findAll());
    }

    public function testHourlyLimitIsPerAuthor(): void
    {
        $tour = $this->tour();

        foreach (range(1, RouteReporter::MAX_PER_HOUR) as $ignored) {
            $this->send($tour, null, 'guest-token');
            $this->clock->sleep(RouteReporter::MIN_SECONDS_BETWEEN);
        }

        try {
            $this->send($tour, null, 'guest-token');
            self::fail('the hourly limit should apply');
        } catch (ReportException $exception) {
            self::assertSame('report.error.too_many', $exception->getMessage());
        }

        $this->send($tour, null, 'another-guest');
        $this->send($tour, $this->user());
        $this->clock->sleep(3600);
        $this->send($tour, null, 'guest-token');
        self::assertCount(RouteReporter::MAX_PER_HOUR + 3, $this->entityManager->getRepository(RouteReport::class)->findAll());
    }

    public function testDeletingTheAuthorDeletesTheirReports(): void
    {
        $author = $this->user();
        $this->send($this->tour($author), $author);

        $this->entityManager->remove($author);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertCount(0, $this->entityManager->getRepository(RouteReport::class)->findAll());
    }
}
