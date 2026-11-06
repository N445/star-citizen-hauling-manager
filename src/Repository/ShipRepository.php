<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ship;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ship>
 */
final class ShipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ship::class);
    }

    public function findSameAs(Ship $ship): ?Ship
    {
        return $this->findOneBy(['slug' => $ship->slug()]);
    }

    public function findOneByFullName(string $fullName): ?Ship
    {
        return $this->createQueryBuilder('ship')
            ->where("CONCAT(ship.manufacturer, ' ', ship.name) = :fullName")
            ->setParameter('fullName', $fullName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Ship>
     */
    public function findAllSorted(): array
    {
        return $this->findBy([], ['manufacturer' => 'ASC', 'name' => 'ASC']);
    }
}
