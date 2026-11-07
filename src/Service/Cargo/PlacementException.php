<?php

declare(strict_types=1);

namespace App\Service\Cargo;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PlacementException extends \DomainException implements TranslatableInterface
{
    public function __construct(string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getMessage(), [], 'messages', $locale);
    }
}
