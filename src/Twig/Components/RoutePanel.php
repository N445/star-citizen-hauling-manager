<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\DoorSide;
use App\Entity\Mission;
use App\Entity\ReportCategory;
use App\Entity\User;
use App\Security\GuestToken;
use App\Service\Report\ReportException;
use App\Service\Report\RouteReporter;
use App\Entity\Tour;
use App\Service\Cargo\CargoEditor;
use App\Service\Cargo\CargoPlan;
use App\Service\Cargo\CargoPlanner;
use App\Service\Cargo\PlacedCrate;
use App\Service\CommodityColors;
use App\Twig\HighlightToken;
use App\Service\Route\Route;
use App\Service\Route\RouteMode;
use App\Service\Route\RoutePlanner;
use App\Service\Tour\QuantityEditor;
use App\Service\Tour\QuantityException;
use App\Service\Tour\ProgressTracker;
use App\Service\Tour\TourWriter;
use App\Security\TourAccessGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
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
        private readonly CargoEditor $cargoEditor,
        private readonly QuantityEditor $quantityEditor,
        private readonly RouteReporter $routeReporter,
        private readonly GuestToken $guestToken,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly CommodityColors $commodityColors,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{doors: list<string>, currentStep: int, unplaced: int, stacked: int, manual: list<int>, crates: list<array<string, mixed>>}
     */
    public function getCargoView(): array
    {
        $route = $this->getRoute();
        $plan = null === $route ? null : (new CargoPlanner())->plan($this->tour, $route);
        $crates = null === $plan ? [] : array_map(fn (PlacedCrate $crate): array => $this->crateView($crate, $plan), $plan->visibleCrates());

        return [
            'doors' => array_map(static fn (DoorSide $door): string => $door->value, $this->tour->doorSides()),
            'currentStep' => $plan?->currentStep ?? 0,
            'unplaced' => $plan?->unplacedCount ?? 0,
            'stacked' => $plan?->stackedCount() ?? 0,
            'manual' => array_values(array_map(static fn (Mission $mission): int => $mission->id(), array_filter($this->tour->missions()->getValues(), static fn (Mission $mission): bool => $mission->hasManualCargo()))),
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
    public function moveCrate(#[LiveArg] int $mission, #[LiveArg] int $index, #[LiveArg] float $x, #[LiveArg] float $y, #[LiveArg] float $z, #[LiveArg] int $width, #[LiveArg] int $length): void
    {
        $this->editCargo(fn (Route $route) => $this->cargoEditor->move($this->tour, $route, $mission, $index, ['x' => $x, 'y' => $y, 'z' => $z, 'width' => $width, 'length' => $length]));
    }

    #[LiveAction]
    public function swapCrates(#[LiveArg] int $mission, #[LiveArg] int $index, #[LiveArg] int $otherMission, #[LiveArg] int $otherIndex): void
    {
        $this->editCargo(fn (Route $route) => $this->cargoEditor->swap($this->tour, $route, $mission, $index, $otherMission, $otherIndex));
    }

    #[LiveAction]
    public function markCrate(#[LiveArg] int $mission, #[LiveArg] int $index, #[LiveArg] int $placed): void
    {
        $this->editCargo(fn (Route $route) => $this->cargoEditor->markPlaced($this->tour, $route, $mission, $index, 1 === $placed));
    }

    #[LiveAction]
    public function resetPlacement(#[LiveArg] int $mission = 0): void
    {
        $this->editCargo(fn (Route $route) => $this->cargoEditor->reset($this->tour, $route, 0 === $mission ? null : $mission));
    }

    #[LiveAction]
    public function setDeliveryQuantity(#[LiveArg] int $delivery, #[LiveArg] string $quantity): void
    {
        $this->editQuantity(fn (): mixed => $this->quantityEditor->setDelivery($this->tour, $delivery, $this->parseQuantity($quantity) ?? $this->tour->delivery($delivery)->quantity()));
    }

    #[LiveAction]
    public function setPickupQuantity(#[LiveArg] int $mission, #[LiveArg] string $location, #[LiveArg] string $quantity): void
    {
        $this->editQuantity(fn (): mixed => $this->quantityEditor->setPickup($this->tour, $mission, $location, $this->parseQuantity($quantity)));
    }

    #[LiveAction]
    public function report(#[LiveArg] string $category, #[LiveArg] string $message): void
    {
        $user = $this->security->getUser();

        try {
            $this->routeReporter->report(
                $this->tour,
                $this->mode,
                ReportCategory::tryFrom($category) ?? ReportCategory::Other,
                $message,
                $user instanceof User ? $user : null,
                $this->guestToken->current(),
                $this->requestStack->getMainRequest()?->getLocale() ?? 'en',
            );
        } catch (ReportException $exception) {
            $this->dispatchBrowserEvent('toast:show', ['message' => $exception->trans($this->translator), 'variant' => 'warning']);

            return;
        }

        $this->dispatchBrowserEvent('toast:show', ['message' => $this->translator->trans('report.sent'), 'variant' => 'success']);
        $this->dispatchBrowserEvent('report:sent');
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
     * Une édition refusée n'est pas une erreur de page : l'interface remet la caisse en place et affiche le motif.
     *
     * @param \Closure(Route): void $edit
     */
    private function editCargo(\Closure $edit): void
    {
        $route = $this->getRoute();

        try {
            if (null !== $route) {
                $edit($route);
            }
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $message = $exception instanceof TranslatableInterface ? $exception->trans($this->translator) : $exception->getMessage();
            $this->dispatchBrowserEvent('cargo:rejected', ['message' => $message]);
        }

        $this->plannedRoute = false;
    }

    /**
     * Une quantité refusée n'est pas une erreur de page : un message, et le champ reprend sa valeur.
     *
     * @param \Closure(): mixed $edit
     */
    private function editQuantity(\Closure $edit): void
    {
        try {
            $this->freezeStopOrder();
            $edit();
            $this->emit('progressChanged');
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $message = $exception instanceof TranslatableInterface ? $exception->trans($this->translator) : $exception->getMessage();
            $this->dispatchBrowserEvent('toast:show', ['message' => $message, 'variant' => 'warning']);
        }

        $this->plannedRoute = false;
    }

    private function parseQuantity(string $quantity): ?int
    {
        $quantity = trim($quantity);

        if ('' === $quantity) {
            return null;
        }

        if (!ctype_digit($quantity) || \strlen($quantity) > 6) {
            throw new QuantityException('quantity.error.range', ['maximum' => QuantityEditor::MAXIMUM]);
        }

        return (int) $quantity;
    }

    /**
     * @return array<string, mixed>
     */
    private function crateView(PlacedCrate $crate, CargoPlan $plan): array
    {
        $currentStep = $plan->currentStep;
        $delivery = $crate->delivery;
        $tokens = [
            HighlightToken::delivery($delivery->id()),
            HighlightToken::mission($delivery->mission()->id()),
            HighlightToken::resource($delivery->resource()),
            HighlightToken::step($crate->pickupStep),
            ...(null === $crate->dropStep ? [] : [HighlightToken::step($crate->dropStep)]),
        ];

        return [
            'missionId' => $delivery->mission()->id(),
            'index' => $plan->indexInMission($crate),
            'rotatable' => $crate->spot->width !== $crate->spot->length,
            'placed' => $crate->placed,
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
