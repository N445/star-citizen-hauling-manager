<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\MissionType;

final readonly class MissionBlueprint
{
    /**
     * @param list<string>                $pickups
     * @param list<array{string, int}>    $drops   lieu de livraison, quantité
     */
    public function __construct(
        private MissionType $type,
        private array $pickups,
        private array $drops,
    ) {
    }

    public static function direct(string $pickup, string $dropoff, int $quantity): self
    {
        return new self(MissionType::Direct, [$pickup], [[$dropoff, $quantity]]);
    }

    public function total(): int
    {
        return array_sum(array_column($this->drops, 1));
    }

    public function build(IdSequence $missionIds, IdSequence $deliveryIds): Mission
    {
        $deliveries = array_map(static fn (array $drop): Delivery => self::delivery($drop, $deliveryIds), $this->drops);
        $mission = new Mission(null, 10_000, $this->type, $this->pickups, $deliveries);
        $missionIds->assign($mission);

        return $mission;
    }

    /**
     * @param array{string, int} $drop
     */
    private static function delivery(array $drop, IdSequence $deliveryIds): Delivery
    {
        $delivery = new Delivery($drop[0], 'Laranite', $drop[1]);
        $deliveryIds->assign($delivery);

        return $delivery;
    }
}
