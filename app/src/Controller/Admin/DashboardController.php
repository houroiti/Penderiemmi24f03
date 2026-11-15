<?php

namespace App\Controller\Admin;

use App\Controller\PenderieDashboardTrait;
use App\Service\StatsService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Super admin back office: the site owner administers everything and follows the KPIs.
 */
#[AdminDashboard(
    routePath: '/{_locale}/admin',
    routeName: 'admin',
    routeOptions: ['requirements' => ['_locale' => '%app.locales%']],
    allowedControllers: [
        UserCrudController::class,
        FollowCrudController::class,
        HomeCrudController::class,
        RoomCrudController::class,
        FurnitureCrudController::class,
        ItemCrudController::class,
        LoanCrudController::class,
    ],
)]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    use PenderieDashboardTrait;

    public function __construct(
        private readonly StatsService $statsService,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $this->statsService->getGlobalStats(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return $this->penderieDashboard('Penderie · Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('menu.dashboard', 'fa fa-chart-line');

        yield MenuItem::section('menu.people');
        yield MenuItem::linkTo(UserCrudController::class, 'menu.users', 'fa fa-users');
        yield MenuItem::linkTo(FollowCrudController::class, 'menu.follows', 'fa fa-user-group');

        yield MenuItem::section('menu.wardrobe');
        yield MenuItem::linkTo(HomeCrudController::class, 'menu.homes', 'fa fa-house');
        yield MenuItem::linkTo(RoomCrudController::class, 'menu.rooms', 'fa fa-door-open');
        yield MenuItem::linkTo(FurnitureCrudController::class, 'menu.furniture', 'fa fa-box-archive');
        yield MenuItem::linkTo(ItemCrudController::class, 'menu.items', 'fa fa-shirt');
        yield MenuItem::linkTo(LoanCrudController::class, 'menu.loans', 'fa fa-handshake');
    }
}
