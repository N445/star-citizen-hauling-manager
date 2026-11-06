<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Mission;
use App\Entity\Tour;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tour>
 */
final class TourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tour::class);
    }

    /**
     * @return list<Tour>
     */
    public function findOwnedBy(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['id' => 'DESC']);
    }

    public function findLatestOwnedBy(User $owner): ?Tour
    {
        return $this->findOneBy(['owner' => $owner], ['id' => 'DESC']);
    }

    public function findOneOfGuest(?string $guestToken): ?Tour
    {
        return null === $guestToken ? null : $this->findOneBy(['guestToken' => $guestToken, 'owner' => null]);
    }

    /**
     * @return list<Tour>
     */
    public function findOrphans(): array
    {
        return $this->findBy(['owner' => null]);
    }

    /**
     * @return list<Mission>
     */
    public function findFinishedMissionsOf(User $owner): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('mission', 'tour')
            ->from(Mission::class, 'mission')
            ->join('mission.tour', 'tour')
            ->where('tour.owner = :owner')
            ->andWhere('mission.finishedAt IS NOT NULL')
            ->orderBy('mission.finishedAt', 'DESC')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getResult();
    }
}
