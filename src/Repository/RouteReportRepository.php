<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RouteReport;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteReport>
 */
final class RouteReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteReport::class);
    }

    /**
     * @return list<\DateTimeImmutable> dates des rapports de cet auteur depuis `$since`, du plus récent au plus ancien
     */
    public function datesSince(?User $author, ?string $guestToken, \DateTimeImmutable $since): array
    {
        $query = $this->createQueryBuilder('report')
            ->where('report.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('report.createdAt', 'DESC');

        if (null !== $author) {
            $query->andWhere('report.author = :author')->setParameter('author', $author);
        } else {
            $query->andWhere('report.author IS NULL AND report.guestToken = :token')->setParameter('token', $guestToken);
        }

        return array_map(static fn (RouteReport $report): \DateTimeImmutable => $report->createdAt(), $query->getQuery()->getResult());
    }
}
