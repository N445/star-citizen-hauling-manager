<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\ContactCategory;
use App\Service\Contact\ContactSender;
use Symfony\Component\Validator\Constraints as Assert;

final class ContactData
{
    #[Assert\NotNull(message: 'contact.category.not_null')]
    public ?ContactCategory $category = ContactCategory::Improvement;

    #[Assert\Email(message: 'contact.email.invalid')]
    #[Assert\Length(max: 180, maxMessage: 'contact.email.too_long')]
    public ?string $replyEmail = null;

    #[Assert\NotBlank(message: 'contact.message.blank')]
    #[Assert\Length(min: 10, max: ContactSender::MAX_MESSAGE_LENGTH, minMessage: 'contact.message.too_short', maxMessage: 'contact.message.too_long')]
    public ?string $message = null;

    public static function forEmail(?string $email): self
    {
        $data = new self();
        $data->replyEmail = $email;

        return $data;
    }
}
