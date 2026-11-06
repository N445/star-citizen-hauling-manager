<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\CallbackTransformer;

/**
 * Les emails sont stockés en minuscules : sans normalisation avant validation, l'unicité laisserait passer une
 * variante en majuscules qui heurterait ensuite l'index unique de la base.
 */
final class EmailNormalizer
{
    public static function transformer(): CallbackTransformer
    {
        return new CallbackTransformer(
            static fn (?string $email): ?string => $email,
            static fn (?string $email): ?string => null === $email ? null : mb_strtolower(trim($email)),
        );
    }
}
