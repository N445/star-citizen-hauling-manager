<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountDeletionFormType;
use App\Form\Data\AccountDeletionData;
use App\Form\Data\ProfileEmailData;
use App\Form\Data\ProfilePasswordData;
use App\Form\ProfileEmailFormType;
use App\Form\ProfilePasswordFormType;
use App\Service\Account\ProfileEditor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly ProfileEditor $profileEditor,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/profile', name: 'profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, #[CurrentUser] User $user): Response
    {
        $emailData = new ProfileEmailData();
        $emailData->email = $user->email();
        $emailForm = $this->createForm(ProfileEmailFormType::class, $emailData)->handleRequest($request);
        $passwordForm = $this->createForm(ProfilePasswordFormType::class, new ProfilePasswordData())->handleRequest($request);
        $deletionForm = $this->createForm(AccountDeletionFormType::class, new AccountDeletionData())->handleRequest($request);

        return $this->changeEmail($emailForm, $user)
            ?? $this->changePassword($passwordForm, $user)
            ?? $this->deleteAccount($deletionForm, $user)
            ?? $this->render('profile/index.html.twig', [
                'emailForm' => $emailForm,
                'passwordForm' => $passwordForm,
                'deletionForm' => $deletionForm,
            ]);
    }

    private function changeEmail(FormInterface $form, User $user): ?Response
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return null;
        }

        $this->profileEditor->changeEmail($user, (string) $form->getData()->email);

        return $this->backToProfile('profile.email.changed');
    }

    private function changePassword(FormInterface $form, User $user): ?Response
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return null;
        }

        $this->profileEditor->changePassword($user, (string) $form->getData()->newPassword);

        return $this->backToProfile('profile.password.changed');
    }

    private function deleteAccount(FormInterface $form, User $user): ?Response
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return null;
        }

        $this->profileEditor->deleteAccount($user);
        $this->addFlash('success', $this->translator->trans('profile.delete.done', [], 'account'));

        return $this->redirectToRoute('home');
    }

    private function backToProfile(string $message): Response
    {
        $this->addFlash('success', $this->translator->trans($message, [], 'account'));

        return $this->redirectToRoute('profile');
    }
}
