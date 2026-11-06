<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Mission;
use App\Entity\User;
use App\Repository\TourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HistoryController extends AbstractController
{
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(TourRepository $tourRepository, #[CurrentUser] User $user): Response
    {
        $missions = $tourRepository->findFinishedMissionsOf($user);

        return $this->render('history/index.html.twig', [
            'missions' => $missions,
            'totalReward' => array_sum(array_map(static fn (Mission $mission): int => $mission->reward(), $missions)),
            'totalDuration' => array_sum(array_map(static fn (Mission $mission): int => $mission->durationInSeconds() ?? 0, $missions)),
        ]);
    }
}
