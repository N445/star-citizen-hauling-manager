<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Relais vers l'Umami auto-hébergé : le navigateur ne parle qu'à notre domaine, sous des chemins neutres,
 * ce qui évite les bloqueurs de pub. Inactif tant que UMAMI_URL ou UMAMI_WEBSITE_ID est vide.
 */
#[Route('/_s')]
final readonly class StatsProxyController
{
    private const int MAX_BODY = 65536;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
        #[Autowire(env: 'UMAMI_URL')]
        private string $umamiUrl,
        #[Autowire(env: 'UMAMI_WEBSITE_ID')]
        private string $websiteId,
    ) {
    }

    #[Route('/s.js', name: 'stats_script', methods: ['GET'])]
    public function script(): Response
    {
        if (!$this->enabled()) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        try {
            $script = $this->cache->get('umami_script', function (ItemInterface $item): string {
                $item->expiresAfter(3600);

                return $this->httpClient->request('GET', $this->endpoint('/script.js'), ['timeout' => 5])->getContent();
            });
        } catch (ExceptionInterface) {
            return new Response('', Response::HTTP_BAD_GATEWAY);
        }

        // le script poste sur {data-host-url}/api/send : on renomme ce chemin, que les listes de blocage connaissent
        return new Response(str_replace('/api/send', '/c', $script), Response::HTTP_OK, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    #[Route('/c', name: 'stats_collect', methods: ['POST'])]
    public function collect(Request $request): Response
    {
        if (!$this->enabled()) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BODY) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $payload = json_decode($body, true);

        // sans ce contrôle, n'importe qui pourrait se servir de notre relais pour alimenter d'autres sites
        if (!\is_array($payload) || ($payload['payload']['website'] ?? null) !== $this->websiteId) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => (string) $request->headers->get('User-Agent', ''),
            'X-Forwarded-For' => $this->clientIp($request),
        ];

        if (null !== $request->headers->get('x-umami-cache')) {
            $headers['x-umami-cache'] = $request->headers->get('x-umami-cache');
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint('/api/send'), ['headers' => $headers, 'body' => $body, 'timeout' => 5]);

            return new Response($response->getContent(false), $response->getStatusCode(), ['Content-Type' => 'application/json']);
        } catch (ExceptionInterface) {
            return new Response('', Response::HTTP_BAD_GATEWAY);
        }
    }

    private function enabled(): bool
    {
        return '' !== $this->umamiUrl && '' !== $this->websiteId;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->umamiUrl, '/').$path;
    }

    /**
     * Derrière Traefik, la dernière adresse de X-Forwarded-For est celle que le proxy a vue.
     */
    private function clientIp(Request $request): string
    {
        $forwarded = array_values(array_filter(array_map('trim', explode(',', (string) $request->headers->get('X-Forwarded-For', '')))));

        return [] === $forwarded ? (string) $request->server->get('REMOTE_ADDR', '') : $forwarded[\count($forwarded) - 1];
    }
}
