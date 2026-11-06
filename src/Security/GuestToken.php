<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Jeton aléatoire gardé par le navigateur d'un visiteur sans compte : il désigne sa tournée unique.
 * Cookie plutôt que localStorage, car le serveur doit le recevoir à chaque requête, Live Components compris.
 */
final readonly class GuestToken
{
    public const string COOKIE = 'hauling_guest';
    private const string NEW_TOKEN = '_hauling_new_guest_token';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function current(): ?string
    {
        $request = $this->requestStack->getMainRequest();

        return $request?->attributes->get(self::NEW_TOKEN) ?? $request?->cookies->get(self::COOKIE);
    }

    public function currentOrNew(): string
    {
        return $this->current() ?? $this->issue();
    }

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        $newToken = $event->getRequest()->attributes->get(self::NEW_TOKEN);

        if (!$event->isMainRequest() || null === $newToken) {
            return;
        }

        $event->getResponse()->headers->setCookie(Cookie::create(self::COOKIE, $newToken, new \DateTimeImmutable('+1 year'))->withSameSite(Cookie::SAMESITE_LAX));
    }

    private function issue(): string
    {
        $token = bin2hex(random_bytes(24));
        $this->requestStack->getMainRequest()?->attributes->set(self::NEW_TOKEN, $token);

        return $token;
    }
}
