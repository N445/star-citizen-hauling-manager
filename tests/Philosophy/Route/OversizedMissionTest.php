<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Route;

use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Tests\Philosophy\Support\RandomTours;
use App\Tests\Philosophy\Support\TourBlueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OversizedMissionTest extends TestCase
{
    /**
     * @return iterable<string, array{TourBlueprint, RouteMode}>
     */
    public static function oversizedTours(): iterable
    {
        foreach (range(1001, 1015) as $seed) {
            $blueprint = (new RandomTours($seed))->routeTour(5);
            $tooSmall = $blueprint->withCapacity($blueprint->largestMission() - 1, 'capacité inférieure à la plus grosse mission');

            foreach (RouteMode::cases() as $mode) {
                yield $tooSmall->name().' · '.$mode->label() => [$tooSmall, $mode];
            }
        }
    }

    #[DataProvider('oversizedTours')]
    public function testMissionLargerThanCapacityIsRefused(TourBlueprint $blueprint, RouteMode $mode): void
    {
        $this->expectException(\DomainException::class);

        (new RoutePlanner())->plan($blueprint->tour(), $mode);
    }
}
