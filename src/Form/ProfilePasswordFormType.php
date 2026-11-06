<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\ProfilePasswordData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProfilePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, ['label' => 'profile.current_password.label', 'attr' => ['autocomplete' => 'current-password']])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'registration.password.mismatch',
                'first_options' => ['label' => 'profile.password.new', 'help' => 'registration.form.password_help', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'registration.form.password_confirmation', 'attr' => ['autocomplete' => 'new-password']],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProfilePasswordData::class, 'translation_domain' => 'account']);
    }
}
