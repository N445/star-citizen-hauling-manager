<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * Jetons partagés entre la 3D et les listes : survoler un élément allume tout ce qui porte le même jeton.
 */
final class HighlightToken
{
    #[AsTwigFunction('mission_token')]
    public static function mission(int $missionId): string
    {
        return 'mission-'.$missionId;
    }

    #[AsTwigFunction('delivery_token')]
    public static function delivery(int $deliveryId): string
    {
        return 'delivery-'.$deliveryId;
    }

    #[AsTwigFunction('resource_token')]
    public static function resource(string $resource): string
    {
        return 'resource-'.preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($resource));
    }

    #[AsTwigFunction('step_token')]
    public static function step(int $stepIndex): string
    {
        return 'step-'.$stepIndex;
    }
}
