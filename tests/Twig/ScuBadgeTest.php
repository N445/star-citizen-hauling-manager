<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class ScuBadgeTest extends KernelTestCase
{
    #[Test]
    public function badgeRendersWithValueAndLimit(): void
    {
        $html = self::getContainer()->get(Environment::class)->render('components/_scu_badge.html.twig', [
            'action' => 'setPickupQuantity',
            'kind' => 'pickup',
            'mission' => 1,
            'location' => 'Lorville',
            'value' => 11,
            'adjusted' => true,
        ]);

        self::assertStringContainsString('11 SCU', $html);
        self::assertStringContainsString('max="10000"', $html);
        self::assertStringContainsString('scu-edit--adjusted', $html);
    }
}
