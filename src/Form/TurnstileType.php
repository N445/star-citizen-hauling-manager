<?php

declare(strict_types=1);

namespace App\Form;

use App\Security\Turnstile\TurnstileVerifier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Case Cloudflare Turnstile. Le script de Cloudflare crée lui-même le champ caché portant le jeton
 * (nom du champ imposé par data-response-field-name) ; la validation se fait à la soumission.
 */
final class TurnstileType extends AbstractType
{
    public function __construct(private readonly TurnstileVerifier $verifier)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            if (!$this->verifier->verify(\is_string($event->getData()) ? $event->getData() : null)) {
                $event->getForm()->addError(new FormError('turnstile.failed', 'turnstile.failed'));
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['site_key'] = $this->verifier->isEnabled() ? $this->verifier->siteKey() : null;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['mapped' => false, 'label' => false, 'required' => false]);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'turnstile';
    }
}
