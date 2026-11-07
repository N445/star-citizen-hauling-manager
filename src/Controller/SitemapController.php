<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * Sitemap et robots.txt générés depuis le routage : toute route publique sans paramètre marquée
 * `options: ['sitemap' => true]` y figure. Les tournées sont privées (jeton ou compte) et n'y sont jamais.
 */
final readonly class SitemapController
{
    public function __construct(
        private RouterInterface $router,
        private Environment $twig,
    ) {
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], format: 'xml')]
    public function sitemap(): Response
    {
        return new Response($this->twig->render('sitemap.xml.twig', ['urls' => $this->urls()]), Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    #[Route('/robots.txt', name: 'robots', methods: ['GET'], format: 'txt')]
    public function robots(): Response
    {
        return new Response($this->twig->render('robots.txt.twig'), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @return list<string>
     */
    private function urls(): array
    {
        $names = array_keys(array_filter(
            $this->router->getRouteCollection()->all(),
            static fn (\Symfony\Component\Routing\Route $route): bool => true === $route->getOption('sitemap'),
        ));

        return array_map(fn (string $name): string => $this->router->generate($name, [], UrlGeneratorInterface::ABSOLUTE_URL), $names);
    }
}
