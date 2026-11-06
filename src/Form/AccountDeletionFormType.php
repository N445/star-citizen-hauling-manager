<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\AccountDeletionData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AccountDeletionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, ['label' => 'profile.current_password.label', 'attr' => ['autocomplete' => 'current-password']])
            ->add('confirmed', CheckboxType::class, ['label' => 'profile.delete.confirmation', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AccountDeletionData::class, 'translation_domain' => 'account']);
    }
}
