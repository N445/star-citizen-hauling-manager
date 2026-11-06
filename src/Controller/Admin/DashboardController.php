<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted(User::ADMIN_ROLE)]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->redirectToRoute('admin_user_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Hauling Manager')
            ->setTranslationDomain('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('menu.site', 'fa fa-arrow-left', 'home');
        yield MenuItem::section('menu.accounts');
        yield MenuItem::linkTo(UserCrudController::class, 'menu.users', 'fa fa-user');
        yield MenuItem::section('menu.planning');
        yield MenuItem::linkTo(TourCrudController::class, 'menu.tours', 'fa fa-route');
        yield MenuItem::section('menu.catalog');
        yield MenuItem::linkTo(ShipCrudController::class, 'menu.ships', 'fa fa-rocket');
        yield MenuItem::linkTo(CommodityCrudController::class, 'menu.commodities', 'fa fa-palette');
    }
}
