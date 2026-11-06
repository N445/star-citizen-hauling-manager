<?php

declare(strict_types=1);

namespace App\Security\Turnstile;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Exige un Turnstile valide avant de vérifier le mot de passe (priorité haute : pas de calcul de hash pour un robot).
 */
#[AsEventListener(event: CheckPassportEvent::class, priority: 1024)]
final readonly class TurnstileLoginListener
{
    public function __construct(
        private TurnstileVerifier $verifier,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$this->verifier->isEnabled() || null === $request || 'app_login' !== $request->attributes->get('_route') || !$request->isMethod('POST')) {
            return;
        }

        if (!$this->verifier->verify($request->request->getString(TurnstileVerifier::RESPONSE_FIELD))) {
            throw new CustomUserMessageAuthenticationException('turnstile.failed');
        }
    }
}
