<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Tour;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Tour>
 */
final class TourVoter extends Voter
{
    public const string ACCESS = 'TOUR_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute && $subject instanceof Tour;
    }

    public function __construct(private readonly GuestToken $guestToken)
    {
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if ($user instanceof User) {
            return $subject->isOwnedBy($user);
        }

        return $subject->belongsToGuest($this->guestToken->current());
    }
}
