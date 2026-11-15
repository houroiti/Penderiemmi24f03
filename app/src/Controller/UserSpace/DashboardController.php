<?php

namespace App\Controller\UserSpace;

use App\Controller\PenderieDashboardTrait;
use App\Entity\User;
use App\Service\StatsService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Mon espace": each user sees information about their own space and manages their things.
 */
#[AdminDashboard(
    routePath: '/{_locale}/mon-espace',
    routeName: 'user_space',
    routeOptions: ['requirements' => ['_locale' => '%app.locales%']],
    allowedControllers: [
        MyHomeCrudController::class,
        MyRoomCrudController::class,
        MyFurnitureCrudController::class,
        MyItemCrudController::class,
        MyLoanCrudController::class,
        MyFollowerCrudController::class,
    ],
)]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractDashboardController
{
    use PenderieDashboardTrait;

    public function __construct(
        private readonly StatsService $statsService,
    ) {
    }

    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('user_space/dashboard.html.twig', [
            'stats' => $this->statsService->getUserStats($user),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return $this->penderieDashboard('Penderie');
    }

    public function configureMenuItems(): iterable
    {
        /** @var User $user */
        $user = $this->getUser();

        yield MenuItem::linkToDashboard('menu.my_space', 'fa fa-house-user');

        yield MenuItem::section('menu.wardrobe');
        yield MenuItem::linkTo(MyItemCrudController::class, 'menu.items', 'fa fa-shirt');
        yield MenuItem::linkTo(MyHomeCrudController::class, 'menu.homes', 'fa fa-house');
        yield MenuItem::linkTo(MyRoomCrudController::class, 'menu.rooms', 'fa fa-door-open');
        yield MenuItem::linkTo(MyFurnitureCrudController::class, 'menu.furniture', 'fa fa-box-archive');
        yield MenuItem::linkTo(MyLoanCrudController::class, 'menu.loans', 'fa fa-handshake');

        if (!$user->isChild()) {
            yield MenuItem::section('menu.people');
            yield MenuItem::linkTo(MyFollowerCrudController::class, 'menu.follows', 'fa fa-user-group');
        }

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::section();
            yield MenuItem::linkToRoute('menu.dashboard', 'fa fa-chart-line', 'admin');
        }
    }
}
