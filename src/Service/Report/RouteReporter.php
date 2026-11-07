<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\ReportCategory;
use App\Entity\RouteReport;
use App\Entity\Tour;
use App\Entity\User;
use App\Repository\RouteReportRepository;
use App\Service\Route\RouteMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Enregistre une erreur déclarée sur une route, avec l'instantané de la tournée. Anti-abus : message borné, puis
 * un délai minimal entre deux rapports et un plafond par heure, par compte ou par jeton invité.
 */
final readonly class RouteReporter
{
    public const int MAX_MESSAGE_LENGTH = 2000;

    public const int MIN_SECONDS_BETWEEN = 30;

    public const int MAX_PER_HOUR = 5;

    public function __construct(
        private RouteSnapshot $snapshot,
        private RouteReportRepository $reports,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ReportException
     */
    public function report(Tour $tour, RouteMode $mode, ReportCategory $category, string $message, ?User $author, ?string $guestToken, string $locale): RouteReport
    {
        $message = trim($message);

        if (null === $author && null === $guestToken) {
            throw new ReportException('report.error.anonymous');
        }

        $this->assertMessage($message);
        $now = $this->clock->now();
        $this->assertNotTooFrequent($author, $guestToken, $now);

        $report = new RouteReport($tour, $author, null === $author ? $guestToken : null, $category, $message, $this->snapshot->capture($tour, $mode, $locale, $now), $now);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    private function assertMessage(string $message): void
    {
        if ('' === $message) {
            throw new ReportException('report.error.empty');
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new ReportException('report.error.too_long', ['maximum' => self::MAX_MESSAGE_LENGTH]);
        }
    }

    private function assertNotTooFrequent(?User $author, ?string $guestToken, \DateTimeImmutable $now): void
    {
        $recent = $this->reports->datesSince($author, $guestToken, $now->modify('-1 hour'));

        if ([] === $recent) {
            return;
        }

        if (\count($recent) >= self::MAX_PER_HOUR) {
            throw new ReportException('report.error.too_many', ['maximum' => self::MAX_PER_HOUR]);
        }

        $wait = self::MIN_SECONDS_BETWEEN - ($now->getTimestamp() - $recent[0]->getTimestamp());

        if ($wait > 0) {
            throw new ReportException('report.error.too_soon', ['seconds' => $wait]);
        }
    }
}
