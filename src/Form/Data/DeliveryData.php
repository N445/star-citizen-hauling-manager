<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Delivery;
use Symfony\Component\Validator\Constraints as Assert;

final class DeliveryData
{
    #[Assert\NotBlank(message: 'delivery.dropoff.not_blank')]
    public ?string $dropoff = null;

    #[Assert\NotBlank(message: 'delivery.resource.not_blank')]
    public ?string $resource = null;

    #[Assert\NotNull(message: 'delivery.quantity.not_null')]
    #[Assert\Positive]
    public ?int $quantity = null;

    public static function fromDelivery(Delivery $delivery): self
    {
        $data = new self();
        $data->dropoff = $delivery->dropoff();
        $data->resource = $delivery->resource();
        $data->quantity = $delivery->quantity();

        return $data;
    }

    public function toDelivery(): Delivery
    {
        return new Delivery($this->dropoff, $this->resource, $this->quantity);
    }
}
