<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\Delivery;
use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\Tour;
use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\CargoPlanner;
use App\Service\Cargo\PlacedCrate;
use App\Service\Route\Route;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Service\Route\Stop;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Photographie d'une tournée à un instant donné : uniquement des scalaires, des listes et des tableaux, jamais
 * d'entité ni d'email, pour pouvoir être stockée en JSON et relue même si la tournée change ou disparaît.
 */
final readonly class RouteSnapshot
{
    public const int VERSION = 1;

    public function __construct(
        private RoutePlanner $routePlanner,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function capture(Tour $tour, RouteMode $mode, string $locale, \DateTimeImmutable $now): array
    {
        [$route, $error] = $this->plan($tour, $mode);
        $cargo = null === $route ? null : (new CargoPlanner())->plan($tour, $route);

        return [
            'version' => self::VERSION,
            'capturedAt' => $now->format(\DATE_ATOM),
            'locale' => $locale,
            'tour' => $this->tour($tour, $mode),
            'missions' => array_map($this->mission(...), $tour->missions()->getValues()),
            'route' => $this->route($route, $error, $cargo),
            'cargo' => $this->cargo($cargo),
        ];
    }

    /**
     * @return array{?Route, ?string}
     */
    private function plan(Tour $tour, RouteMode $mode): array
    {
        try {
            return [$this->routePlanner->plan($tour, $mode), null];
        } catch (\DomainException $exception) {
            return [null, $exception instanceof TranslatableInterface ? $exception->trans($this->translator, 'en') : $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tour(Tour $tour, RouteMode $mode): array
    {
        $ship = $tour->ship();
        $doors = static fn (array $doors): array => array_map(static fn (DoorSide $door): string => $door->value, $doors);

        return [
            'id' => $tour->id(),
            'name' => $tour->name(),
            'mode' => $mode->value,
            'startLocation' => $tour->startLocation(),
            'stopOrder' => $tour->stopOrder(),
            'capacity' => $tour->capacity(),
            'shipName' => $tour->shipName(),
            'ship' => null === $ship ? null : [
                'slug' => $ship->slug(),
                'manufacturer' => $ship->manufacturer(),
                'name' => $ship->name(),
                'capacity' => $ship->capacity(),
                'mainDoor' => $ship->mainDoor()->value,
                'doors' => $doors($ship->doorSides()),
                'cargoGroups' => $ship->cargoGroups(),
            ],
            'doors' => $doors($tour->doorSides()),
            'doorsOverridden' => $tour->hasDoorOverride(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mission(Mission $mission): array
    {
        return [
            'id' => $mission->id(),
            'name' => $mission->name(),
            'type' => $mission->type()->value,
            'reward' => $mission->reward(),
            'pickups' => $mission->pickups(),
            'pickedUpAt' => array_values(array_filter($mission->pickups(), $mission->isPickedUpAt(...))),
            'pickupQuantities' => $mission->pickupQuantities(),
            'done' => $mission->isDone(),
            'startedAt' => $mission->startedAt()?->format(\DATE_ATOM),
            'finishedAt' => $mission->finishedAt()?->format(\DATE_ATOM),
            'deliveries' => array_map(static fn (Delivery $delivery): array => [
                'id' => $delivery->id(),
                'dropoff' => $delivery->dropoff(),
                'resource' => $delivery->resource(),
                'quantity' => $delivery->quantity(),
                'actualQuantity' => $delivery->hasActualQuantity() ? $delivery->effectiveQuantity() : null,
                'delivered' => $delivery->isDelivered(),
            ], $mission->deliveries()->getValues()),
            'cargoPlacement' => $mission->frozenCargo(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function route(?Route $route, ?string $error, ?CargoPlan $cargo): array
    {
        if (null === $route) {
            return ['error' => $error, 'start' => null, 'stops' => [], 'currentStep' => null, 'customOrder' => false, 'started' => false, 'finished' => false, 'moveCount' => 0];
        }

        return [
            'error' => null,
            'start' => $route->start,
            'customOrder' => $route->customOrder,
            'started' => $route->started,
            'finished' => $route->isFinished(),
            'currentStep' => $cargo?->currentStep,
            'moveCount' => $route->moveCount(),
            'stops' => array_map(static fn (Stop $stop, int $index): array => [
                'index' => $index,
                'location' => $stop->location,
                'load' => $stop->load,
                'done' => $stop->isDone(),
                'pickups' => array_map(static fn (Mission $mission): array => ['mission' => $mission->id(), 'done' => $mission->isPickedUpAt($stop->location)], $stop->pickups),
                'deliveries' => array_map(static fn (Delivery $delivery): array => ['delivery' => $delivery->id(), 'mission' => $delivery->mission()->id(), 'resource' => $delivery->resource(), 'quantity' => $delivery->effectiveQuantity(), 'done' => $delivery->isDelivered()], $stop->deliveries),
            ], $route->stops, array_keys($route->stops)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cargo(?CargoPlan $plan): array
    {
        if (null === $plan) {
            return ['crates' => [], 'unplaced' => 0, 'stacked' => 0];
        }

        return [
            'unplaced' => $plan->unplacedCount,
            'stacked' => $plan->stackedCount(),
            'crates' => array_map(static fn (PlacedCrate $crate): array => [
                'mission' => $crate->delivery->mission()->id(),
                'delivery' => $crate->delivery->id(),
                'index' => $plan->indexInMission($crate),
                'resource' => $crate->delivery->resource(),
                'size' => $crate->size,
                'x' => $crate->spot->x(),
                'y' => $crate->spot->y(),
                'z' => $crate->spot->z(),
                'width' => $crate->spot->width,
                'height' => $crate->spot->height,
                'length' => $crate->spot->length,
                'pickupLocation' => $crate->pickupLocation,
                'pickupStep' => $crate->pickupStep,
                'dropStep' => $crate->dropStep,
                'manual' => $crate->manual,
                'placed' => $crate->placed,
                'stacked' => $crate->stackedOnEarlierDelivery,
                'phantom' => $crate->isPhantom(),
            ], $plan->crates),
        ];
    }
}
