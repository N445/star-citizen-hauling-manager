<?php

declare(strict_types=1);

namespace App\Service\Location;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LocationException extends \DomainException implements TranslatableInterface
{
    /**
     * @param array<string, string|int> $parameters
     */
    public function __construct(string $translationKey, private readonly array $parameters = [])
    {
        parent::__construct($translationKey);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        // Le message finit dans un flash EasyAdmin affiché en `|raw` : les noms saisis sont échappés ici.
        $parameters = array_map(static fn (string|int $value): string|int => 'string' === gettype($value) ? htmlspecialchars($value) : $value, $this->parameters);

        return $translator->trans($this->getMessage(), $parameters, 'admin', $locale);
    }
}
