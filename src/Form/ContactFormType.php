<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContactCategory;
use App\Form\Data\ContactData;
use App\Service\Contact\ContactSender;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EnumType::class, [
                'class' => ContactCategory::class,
                'label' => 'contact.form.category',
                'choice_label' => static fn (ContactCategory $category): string => $category->label(),
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message',
                'help' => 'contact.form.message_help',
                'attr' => ['rows' => 7, 'maxlength' => ContactSender::MAX_MESSAGE_LENGTH],
            ])
            ->add('replyEmail', EmailType::class, [
                'label' => 'contact.form.email',
                'help' => 'contact.form.email_help',
                'required' => false,
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('turnstile', TurnstileType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactData::class,
            'translation_domain' => 'messages',
        ]);
    }
}
