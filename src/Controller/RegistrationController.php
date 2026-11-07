<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\Data\RegistrationData;
use App\Form\RegistrationFormType;
use App\Security\UserRegistration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', options: ['sitemap' => true], methods: ['GET', 'POST'])]
    public function register(Request $request, UserRegistration $userRegistration, TranslatorInterface $translator): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $data = new RegistrationData();
        $form = $this->createForm(RegistrationFormType::class, $data)->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('registration/register.html.twig', ['registrationForm' => $form]);
        }

        $userRegistration->register($data);
        $this->addFlash('success', $translator->trans('flash.registered', domain: 'account'));

        return $this->redirectToRoute('home');
    }
}
