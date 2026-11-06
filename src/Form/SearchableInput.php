<?php

declare(strict_types=1);

namespace App\Form;

use App\Catalog\LocationCatalog;

final class SearchableInput
{
    /**
     * @param list<string|array{value: string, text: string, color?: string}> $suggestions
     *
     * @return array<string, string>
     */
    public static function attributes(array $suggestions, bool $multiple = false, bool $badges = false): array
    {
        return [
            'data-controller' => 'tom-select',
            'data-tom-select-options-value' => json_encode($suggestions, JSON_THROW_ON_ERROR),
            'data-tom-select-multiple-value' => $multiple ? 'true' : 'false',
            'data-tom-select-badges-value' => $badges ? 'true' : 'false',
            'autocomplete' => 'off',
        ];
    }

    /** @return array<string, string> */
    public static function locations(bool $multiple = false): array
    {
        return self::attributes(LocationCatalog::suggestions(), $multiple) + [
            'data-tom-select-optgroups-value' => json_encode(LocationCatalog::optgroups(), JSON_THROW_ON_ERROR),
        ];
    }
}
