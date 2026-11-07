<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Tour;
use App\Entity\User;
use App\Security\GuestToken;
use App\Security\TourVoter;
use App\Tests\Philosophy\Support\IdSequence;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Le signalement d'une route passe par le même accès que la tournée : seul son propriétaire (ou le détenteur du jeton
 * invité) peut en déclarer une.
 */
final class TourVoterTest extends TestCase
{
    private IdSequence $ids;

    protected function setUp(): void
    {
        $this->ids = new IdSequence();
    }

    private function voter(?string $guestCookie): TourVoter
    {
        $stack = new RequestStack();
        $stack->push(new Request(cookies: null === $guestCookie ? [] : [GuestToken::COOKIE => $guestCookie]));

        return new TourVoter(new GuestToken($stack));
    }

    private function user(string $email): User
    {
        $user = new User($email);
        $this->ids->assign($user);

        return $user;
    }

    private function tour(): Tour
    {
        return new Tour('T', 'Cube', 10, null);
    }

    public function testOwnerMayAccessTheTour(): void
    {
        $owner = $this->user('owner@example.test');
        $tour = $this->tour();
        $tour->assignTo($owner);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter(null)->vote(new UsernamePasswordToken($owner, 'main', []), $tour, [TourVoter::ACCESS]));
    }

    public function testAnotherAccountIsDenied(): void
    {
        $tour = $this->tour();
        $tour->assignTo($this->user('owner@example.test'));

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter('whatever')->vote(new UsernamePasswordToken($this->user('other@example.test'), 'main', []), $tour, [TourVoter::ACCESS]));
    }

    public function testGuestWithTheRightTokenMayAccess(): void
    {
        $tour = $this->tour();
        $tour->entrustToGuest('guest-token');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter('guest-token')->vote(new NullToken(), $tour, [TourVoter::ACCESS]));
    }

    public function testGuestWithAnotherTokenOrNoneIsDenied(): void
    {
        $tour = $this->tour();
        $tour->entrustToGuest('guest-token');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter('someone-else')->vote(new NullToken(), $tour, [TourVoter::ACCESS]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter(null)->vote(new NullToken(), $tour, [TourVoter::ACCESS]));
    }
}
