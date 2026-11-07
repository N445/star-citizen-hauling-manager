<?php

declare(strict_types=1);

namespace App\Service\Contact;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactException extends \DomainException implements TranslatableInterface
{
    /**
     * @param array<string, int> $parameters
     */
    public function __construct(string $translationKey, private readonly array $parameters = [])
    {
        parent::__construct($translationKey);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getMessage(), $this->parameters, 'messages', $locale);
    }
}
