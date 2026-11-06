<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\DoorSide;
use App\Entity\Tour;
use Symfony\Component\Validator\Constraints as Assert;

final class TourData
{
    #[Assert\NotBlank(message: 'tour.name.not_blank')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'tour.ship_name.not_blank')]
    public ?string $shipName = null;

    #[Assert\NotNull(message: 'tour.capacity.not_null')]
    #[Assert\Positive]
    public ?int $capacity = null;

    public ?string $startLocation = null;

    /** @var list<DoorSide> */
    #[Assert\Count(min: 1, minMessage: 'tour.door_sides.count')]
    public array $doorSides = [DoorSide::Rear];

    public static function fromTour(Tour $tour): self
    {
        $data = new self();
        $data->name = $tour->name();
        $data->shipName = $tour->shipName();
        $data->capacity = $tour->capacity();
        $data->startLocation = $tour->startLocation();
        $data->doorSides = $tour->doorSides();

        return $data;
    }
}
