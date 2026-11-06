<?php

declare(strict_types=1);

namespace App\Security\Turnstile;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérifie un jeton Cloudflare Turnstile côté serveur. Inactif tant que les deux clés sont vides (développement).
 * Si Cloudflare est injoignable, la vérification échoue : mieux vaut bloquer une connexion qu'ouvrir la porte aux robots.
 */
final readonly class TurnstileVerifier
{
    public const string RESPONSE_FIELD = 'cf-turnstile-response';

    private const string ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'TURNSTILE_SITE_KEY')]
        private string $siteKey,
        #[Autowire(env: 'TURNSTILE_SECRET_KEY')]
        private string $secretKey,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->siteKey && '' !== $this->secretKey;
    }

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    public function verify(?string $token): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (null === $token || '' === $token) {
            return false;
        }

        try {
            $result = $this->httpClient->request('POST', self::ENDPOINT, [
                'body' => ['secret' => $this->secretKey, 'response' => $token],
                'timeout' => 5,
            ])->toArray();
        } catch (ExceptionInterface) {
            return false;
        }

        return true === ($result['success'] ?? false);
    }
}
