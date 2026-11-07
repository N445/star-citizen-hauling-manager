<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContactCategory;
use App\Entity\User;
use App\Form\ContactFormType;
use App\Form\Data\ContactData;
use App\Security\GuestToken;
use App\Service\Contact\ContactException;
use App\Service\Contact\ContactSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly ContactSender $sender,
        private readonly GuestToken $guestToken,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/contact', name: 'contact', options: ['sitemap' => true], methods: ['GET', 'POST'])]
    public function contact(Request $request, #[CurrentUser] ?User $user): Response
    {
        $data = ContactData::forEmail($user?->email());
        $form = $this->createForm(ContactFormType::class, $data)->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('contact/index.html.twig', ['contactForm' => $form]);
        }

        try {
            $this->send($data, $user, $request->getLocale());
        } catch (ContactException $exception) {
            return $this->rejected($form, $exception);
        }

        $this->addFlash('success', 'contact.flash.sent');

        return $this->redirectToRoute('contact');
    }

    private function send(ContactData $data, ?User $user, string $locale): void
    {
        $guestToken = null === $user ? $this->guestToken->currentOrNew() : '';
        $this->sender->send($data->category ?? ContactCategory::Other, (string) $data->message, $data->replyEmail, $user, $guestToken, $locale);
    }

    private function rejected(FormInterface $form, ContactException $exception): Response
    {
        $form->addError(new FormError($exception->trans($this->translator)));

        return $this->render('contact/index.html.twig', ['contactForm' => $form], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
    }
}
