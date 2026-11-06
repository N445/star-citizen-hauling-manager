<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\DoorSide;
use App\Entity\Tour;
use App\Service\Cargo\CargoPlanner;
use App\Service\Cargo\PlacedCrate;
use App\Service\CommodityColors;
use App\Twig\HighlightToken;
use App\Service\Route\Route;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Service\Tour\ProgressTracker;
use App\Service\Tour\TourWriter;
use App\Security\TourAccessGuard;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class RoutePanel
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public Tour $tour;

    #[LiveProp(writable: true)]
    public RouteMode $mode = RouteMode::FewestStops;

    public string|TranslatableInterface|null $error = null;

    private Route|false|null $plannedRoute = false;

    private TourAccessGuard $tourAccessGuard;

    public function __construct(
        private readonly RoutePlanner $routePlanner,
        private readonly ProgressTracker $progressTracker,
        private readonly TourWriter $tourWriter,
        private readonly CommodityColors $commodityColors,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{doors: list<string>, currentStep: int, unplaced: int, stacked: int, crates: list<array<string, mixed>>}
     */
    public function getCargoView(): array
    {
        $route = $this->getRoute();
        $plan = null === $route ? null : (new CargoPlanner())->plan($this->tour, $route);
        $crates = null === $plan ? [] : array_map(fn (PlacedCrate $crate): array => $this->crateView($crate, $plan->currentStep), $plan->visibleCrates());

        return [
            'doors' => array_map(static fn (DoorSide $door): string => $door->value, $this->tour->doorSides()),
            'currentStep' => $plan?->currentStep ?? 0,
            'unplaced' => $plan?->unplacedCount ?? 0,
            'stacked' => $plan?->stackedCount() ?? 0,
            'crates' => $crates,
        ];
    }

    #[LiveAction]
    public function reorder(#[LiveArg] string $locations): void
    {
        $this->tourWriter->reorderStops($this->tour, array_values(array_filter(json_decode($locations, true, flags: JSON_THROW_ON_ERROR), \is_string(...))));
        $this->emit('progressChanged');
    }

    #[LiveAction]
    public function resetOrder(): void
    {
        $this->tourWriter->resetStopOrder($this->tour);
        $this->emit('progressChanged');
    }

    #[LiveAction]
    public function toggleDelivery(#[LiveArg] int $id): void
    {
        $this->freezeStopOrder();
        $this->progressTracker->toggleDelivery($this->tour, $id);
        $this->plannedRoute = false;
        $this->emit('progressChanged');
    }

    #[LiveAction]
    public function togglePickup(#[LiveArg] int $mission, #[LiveArg] string $location): void
    {
        $this->freezeStopOrder();
        $proposedPlacement = null === $this->getRoute() ? [] : (new CargoPlanner())->plan($this->tour, $this->getRoute())->placementOf($mission);
        $this->progressTracker->togglePickup($this->tour, $mission, $location, $proposedPlacement);
        $this->plannedRoute = false;
        $this->emit('progressChanged');
    }

    #[Required]
    public function useTourAccessGuard(TourAccessGuard $tourAccessGuard): void
    {
        $this->tourAccessGuard = $tourAccessGuard;
    }

    #[PostHydrate]
    public function denyForeignTour(): void
    {
        $this->tourAccessGuard->denyUnlessOwner($this->tour);
    }

    #[LiveListener('missionsChanged')]
    public function refresh(): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    private function crateView(PlacedCrate $crate, int $currentStep): array
    {
        $delivery = $crate->delivery;
        $tokens = [
            HighlightToken::delivery($delivery->id()),
            HighlightToken::mission($delivery->mission()->id()),
            HighlightToken::resource($delivery->resource()),
            HighlightToken::step($crate->pickupStep),
            ...(null === $crate->dropStep ? [] : [HighlightToken::step($crate->dropStep)]),
        ];

        return [
            'x' => $crate->spot->x(),
            'y' => $crate->spot->y(),
            'z' => $crate->spot->z(),
            'width' => $crate->spot->width,
            'height' => $crate->spot->height,
            'length' => $crate->spot->length,
            'color' => $this->commodityColors->of($delivery->resource()),
            'key' => HighlightToken::delivery($delivery->id()),
            'tokens' => $tokens,
            'phantom' => $crate->isPhantom(),
            'unloading' => $crate->isUnloadingAt($currentStep),
            'stacked' => $crate->stackedOnEarlierDelivery,
            'size' => $crate->size,
            'resource' => $delivery->resource(),
            'mission' => $this->tour->missionLabel($delivery->mission()),
            'dropoff' => $delivery->dropoff(),
            'label' => $this->translator->trans($crate->stackedOnEarlierDelivery ? 'cargo.crate.label_stacked' : 'cargo.crate.label', [
                'size' => $crate->size,
                'resource' => $delivery->resource(),
                'mission' => $this->tour->missionLabel($delivery->mission()),
                'dropoff' => $delivery->dropoff(),
            ]),
        ];
    }

    public function getRoute(): ?Route
    {
        if (false === $this->plannedRoute) {
            $this->plannedRoute = $this->planRoute();
        }

        return $this->plannedRoute;
    }

    /**
     * Dès la première action cochée, l'ordre affiché devient celui de la tournée : plus rien ne bouge en la suivant.
     */
    private function freezeStopOrder(): void
    {
        $route = $this->getRoute();

        if (null === $route || null !== $this->tour->stopOrder()) {
            return;
        }

        $this->tourWriter->reorderStops($this->tour, $route->stopLocations());
    }

    private function planRoute(): ?Route
    {
        try {
            return $this->routePlanner->plan($this->tour, $this->mode);
        } catch (\DomainException $exception) {
            $this->error = $exception instanceof TranslatableInterface ? $exception : $exception->getMessage();

            return null;
        }
    }
}
