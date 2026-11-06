<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
final class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * Tout l'arbre en une requête : les parents et enfants sont ensuite résolus en mémoire.
     *
     * @return list<Location>
     */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('location')
            ->leftJoin('location.children', 'child')
            ->addSelect('child')
            ->orderBy('location.name', 'ASC')
            ->addOrderBy('child.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
