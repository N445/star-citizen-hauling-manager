<?php

declare(strict_types=1);

namespace App\Service\Route;

interface StopPolicy
{
    /**
     * @return list<int> les états possibles après l'arrêt (plusieurs quand il y a un choix à faire)
     */
    public function stopAt(string $location, int $done): array;
}
