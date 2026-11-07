<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ReportStatus;
use App\Entity\User;
use App\Repository\ContactMessageRepository;
use App\Repository\RouteReportRepository;
use App\Service\Admin\AdminOverview;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted(User::ADMIN_ROLE)]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminOverview $overview,
        private readonly ContactMessageRepository $contactMessages,
        private readonly RouteReportRepository $routeReports,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', ['overview' => $this->overview]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Hauling Manager')
            ->setTranslationDomain('admin');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addWebpackEncoreEntry('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('menu.site', 'fa fa-arrow-left', 'home');
        yield MenuItem::linkToDashboard('menu.dashboard', 'fa fa-gauge');
        yield MenuItem::section('menu.accounts');
        yield MenuItem::linkTo(UserCrudController::class, 'menu.users', 'fa fa-user');
        yield MenuItem::section('menu.planning');
        yield MenuItem::linkTo(TourCrudController::class, 'menu.tours', 'fa fa-route');
        yield MenuItem::linkTo(RouteReportCrudController::class, 'menu.reports', 'fa fa-flag')->setBadge($this->openBadge($this->routeReports->count(['status' => ReportStatus::Open])), 'warning');
        yield MenuItem::linkTo(ContactMessageCrudController::class, 'menu.contact', 'fa fa-envelope')->setBadge($this->openBadge($this->contactMessages->countOpen()), 'warning');
        yield MenuItem::section('menu.catalog');
        yield MenuItem::linkTo(ShipCrudController::class, 'menu.ships', 'fa fa-rocket');
        yield MenuItem::linkTo(CommodityCrudController::class, 'menu.commodities', 'fa fa-palette');
        yield MenuItem::linkToRoute('menu.locations', 'fa fa-location-dot', 'admin_locations_index');
    }

    private function openBadge(int $open): ?int
    {
        return 0 === $open ? null : $open;
    }
}
