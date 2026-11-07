<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Commodity;
use App\Entity\ContactMessage;
use App\Entity\Delivery;
use App\Entity\Location;
use App\Entity\Mission;
use App\Entity\ReportStatus;
use App\Entity\RouteReport;
use App\Entity\Ship;
use App\Entity\Tour;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Chiffres globaux du tableau de bord admin : lecture seule, une requête agrégée par chiffre.
 */
final readonly class AdminOverview
{
    private const int TOP = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function users(): array
    {
        $now = $this->clock->now();

        return [
            'total' => $this->count(User::class),
            'last7Days' => $this->scalar('SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.registeredAt >= :since', ['since' => $now->modify('-7 days')]),
            'last30Days' => $this->scalar('SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.registeredAt >= :since', ['since' => $now->modify('-30 days')]),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function tours(): array
    {
        return [
            'total' => $this->count(Tour::class),
            'withAccount' => $this->scalar('SELECT COUNT(t.id) FROM '.Tour::class.' t WHERE t.owner IS NOT NULL'),
            'anonymous' => $this->scalar('SELECT COUNT(t.id) FROM '.Tour::class.' t WHERE t.owner IS NULL AND t.guestToken IS NOT NULL'),
            'orphan' => $this->scalar('SELECT COUNT(t.id) FROM '.Tour::class.' t WHERE t.owner IS NULL AND t.guestToken IS NULL'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function missions(): array
    {
        return [
            'total' => $this->count(Mission::class),
            'inProgress' => $this->scalar('SELECT COUNT(m.id) FROM '.Mission::class.' m WHERE m.startedAt IS NOT NULL AND m.finishedAt IS NULL'),
            'finished' => $this->scalar('SELECT COUNT(m.id) FROM '.Mission::class.' m WHERE m.finishedAt IS NOT NULL'),
            'finishedLast7Days' => $this->scalar('SELECT COUNT(m.id) FROM '.Mission::class.' m WHERE m.finishedAt >= :since', ['since' => $this->clock->now()->modify('-7 days')]),
            'rewardFinished' => $this->scalar('SELECT COALESCE(SUM(m.reward), 0) FROM '.Mission::class.' m WHERE m.finishedAt IS NOT NULL'),
            'scuDelivered' => $this->scalar('SELECT COALESCE(SUM(COALESCE(d.actualQuantity, d.quantity)), 0) FROM '.Delivery::class.' d WHERE d.delivered = true'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function feedback(): array
    {
        return [
            'reportsOpen' => $this->count(RouteReport::class, ['status' => ReportStatus::Open]),
            'reportsTotal' => $this->count(RouteReport::class),
            'contactOpen' => $this->count(ContactMessage::class, ['status' => ReportStatus::Open]),
            'contactTotal' => $this->count(ContactMessage::class),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function catalog(): array
    {
        return [
            'ships' => $this->count(Ship::class),
            'commodities' => $this->count(Commodity::class),
            'locations' => $this->count(Location::class),
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    public function topShips(): array
    {
        return $this->ranking('SELECT t.shipName AS label, COUNT(t.id) AS value FROM '.Tour::class.' t GROUP BY t.shipName ORDER BY value DESC, label ASC');
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    public function topCommodities(): array
    {
        return $this->ranking('SELECT d.resource AS label, SUM(COALESCE(d.actualQuantity, d.quantity)) AS value FROM '.Delivery::class.' d GROUP BY d.resource ORDER BY value DESC, label ASC');
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    public function topDropoffs(): array
    {
        return $this->ranking('SELECT d.dropoff AS label, COUNT(d.id) AS value FROM '.Delivery::class.' d GROUP BY d.dropoff ORDER BY value DESC, label ASC');
    }

    /**
     * @return list<User>
     */
    public function latestUsers(): array
    {
        return $this->entityManager->getRepository(User::class)->findBy([], ['registeredAt' => 'DESC'], self::TOP);
    }

    /**
     * @return list<ContactMessage>
     */
    public function latestContacts(): array
    {
        return $this->entityManager->getRepository(ContactMessage::class)->findBy([], ['id' => 'DESC'], self::TOP);
    }

    /**
     * @param class-string $entity
     * @param array<string, mixed> $criteria
     */
    private function count(string $entity, array $criteria = []): int
    {
        return $this->entityManager->getRepository($entity)->count($criteria);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function scalar(string $dql, array $parameters = []): int
    {
        return (int) $this->entityManager->createQuery($dql)->setParameters($parameters)->getSingleScalarResult();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function ranking(string $dql): array
    {
        $rows = $this->entityManager->createQuery($dql)->setMaxResults(self::TOP)->getArrayResult();

        return array_map(static fn (array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['value']], $rows);
    }
}
