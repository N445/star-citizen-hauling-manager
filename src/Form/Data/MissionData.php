<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Mission;
use App\Entity\MissionType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MissionData
{
    public ?string $name = null;

    #[Assert\NotNull(message: 'mission.reward.not_null')]
    #[Assert\PositiveOrZero]
    public ?int $reward = null;

    #[Assert\NotNull(message: 'mission.type.not_null')]
    public ?MissionType $type = null;

    /** @var list<string> */
    #[Assert\Count(min: 1, minMessage: 'mission.pickups.count')]
    public array $pickups = [];

    /** @var list<DeliveryData> */
    #[Assert\Count(min: 1, minMessage: 'mission.deliveries.count')]
    #[Assert\Valid]
    public array $deliveries = [];

    public static function fromMission(Mission $mission): self
    {
        $data = new self();
        $data->name = $mission->name();
        $data->reward = $mission->reward();
        $data->type = $mission->type();
        $data->pickups = $mission->pickups();
        $data->deliveries = array_map(DeliveryData::fromDelivery(...), $mission->deliveries()->getValues());

        return $data;
    }

    #[Assert\Callback]
    public function validateType(ExecutionContextInterface $context): void
    {
        if (null === $this->type || [] === $this->pickups || [] === $this->deliveries) {
            return;
        }

        if ($this->type->accepts(\count(array_unique($this->pickups)), $this->dropoffCount())) {
            return;
        }

        $context->buildViolation($this->type->rule())->addViolation();
    }

    public static function blank(): self
    {
        $data = new self();
        $data->deliveries = [new DeliveryData()];

        return $data;
    }

    public function toMission(): Mission
    {
        return new Mission($this->name, $this->reward, $this->type, $this->pickups, $this->toDeliveries());
    }

    public function applyTo(Mission $mission): void
    {
        $mission->update($this->name, $this->reward, $this->type, $this->pickups, $this->toDeliveries());
    }

    /**
     * @return list<\App\Entity\Delivery>
     */
    private function toDeliveries(): array
    {
        return array_values(array_map(static fn (DeliveryData $delivery) => $delivery->toDelivery(), $this->deliveries));
    }

    private function dropoffCount(): int
    {
        return \count(array_unique(array_map(static fn (DeliveryData $delivery) => $delivery->dropoff, $this->deliveries)));
    }
}
