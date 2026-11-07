<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mentions légales et politique de confidentialité (RGPD). Textes longs : un gabarit par langue plutôt que des clés
 * de traduction. Éditeur sous pseudonyme, serveur auto-hébergé : choix de l'utilisateur de ne publier aucune adresse.
 */
final class LegalController extends AbstractController
{
    #[Route('/legal', name: 'legal_notice', options: ['sitemap' => true], methods: ['GET'])]
    public function notice(Request $request): Response
    {
        return $this->page('notice', $request);
    }

    #[Route('/privacy', name: 'legal_privacy', options: ['sitemap' => true], methods: ['GET'])]
    public function privacy(Request $request): Response
    {
        return $this->page('privacy', $request);
    }

    private function page(string $page, Request $request): Response
    {
        return $this->render('legal/page.html.twig', [
            'page' => $page,
            'content' => sprintf('legal/%s.%s.html.twig', $page, 'fr' === $request->getLocale() ? 'fr' : 'en'),
            'updatedAt' => new \DateTimeImmutable('2026-11-07'),
        ]);
    }
}
