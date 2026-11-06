<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\RegistrationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'registration.form.email',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'registration.password.mismatch',
                'first_options' => [
                    'label' => 'registration.form.password',
                    'help' => 'registration.form.password_help',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'registration.form.password_confirmation',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ]);

        $builder->get('email')->addModelTransformer(EmailNormalizer::transformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationData::class,
            'translation_domain' => 'account',
        ]);
    }
}
