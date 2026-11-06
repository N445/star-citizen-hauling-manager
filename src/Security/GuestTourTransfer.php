<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\TourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * À l'inscription comme à la connexion, la tournée en cours du visiteur passe sur son compte.
 */
final readonly class GuestTourTransfer
{
    public function __construct(
        private GuestToken $guestToken,
        private TourRepository $tourRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $guestTour = $this->tourRepository->findOneOfGuest($this->guestToken->current());

        if (!$user instanceof User || null === $guestTour) {
            return;
        }

        $guestTour->assignTo($user);
        $this->entityManager->flush();
    }
}
