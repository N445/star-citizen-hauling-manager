<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\ProfileEmailData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProfileEmailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'profile.email.new', 'attr' => ['autocomplete' => 'email']])
            ->add('currentPassword', PasswordType::class, ['label' => 'profile.current_password.label', 'attr' => ['autocomplete' => 'current-password']]);

        $builder->get('email')->addModelTransformer(EmailNormalizer::transformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProfileEmailData::class, 'translation_domain' => 'account']);
    }
}
