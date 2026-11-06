<?php

declare(strict_types=1);

namespace App\Controller;

use App\EventSubscriber\SessionLocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'locale_switch', requirements: ['locale' => 'en|fr'], methods: ['GET'])]
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $request->getSession()->set(SessionLocaleSubscriber::SESSION_KEY, $locale);

        return $this->redirect($this->previousPage($request));
    }

    private function previousPage(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');

        if (str_starts_with($referer, $request->getSchemeAndHttpHost().'/')) {
            return $referer;
        }

        return $this->generateUrl('home');
    }
}
