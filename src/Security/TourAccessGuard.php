<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Tour;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class TourAccessGuard
{
    public function __construct(private Security $security)
    {
    }

    public function denyUnlessOwner(Tour $tour): void
    {
        if ($this->security->isGranted(TourVoter::ACCESS, $tour)) {
            return;
        }

        throw new AccessDeniedException();
    }
}
