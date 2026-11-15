<?php

namespace App\Controller\UserSpace;

use App\Entity\Home;
use App\Entity\User;
use App\Repository\ItemRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractOwnedCrudController<Home>
 */
final class MyHomeCrudController extends AbstractOwnedCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ItemRepository $itemRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Home::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('entity.home.singular')
            ->setEntityLabelInPlural('entity.home.plural');
    }

    public function configureActions(Actions $actions): Actions
    {
        // shortcut: add a room to this home, the home is already chosen in the form
        $addRoom = Action::new('addRoom', 'action.add_room', 'fa fa-plus')
            ->linkToUrl(fn (Home $home): string => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(MyRoomCrudController::class)
                ->setAction(Action::NEW)
                ->set('home', $home->getId())
                ->generateUrl());

        // deleting a home also deletes its rooms and furniture: say it before, with the real figures
        $confirmDelete = fn (Action $action): Action => $action->askConfirmation(
            fn (Home $home): TranslatableMessage => new TranslatableMessage('confirm.delete_home', [
                '%name%' => $home->getName(),
                '%rooms%' => $home->getRooms()->count(),
                '%furniture%' => array_sum($home->getRooms()->map(static fn ($room): int => $room->getFurniture()->count())->toArray()),
                '%items%' => $this->itemRepository->countStoredIn($home),
            ]),
        );

        return $actions
            ->add(Crud::PAGE_INDEX, $addRoom)
            ->add(Crud::PAGE_DETAIL, $addRoom)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, $confirmDelete)
            ->update(Crud::PAGE_DETAIL, Action::DELETE, $confirmDelete);
    }

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder->andWhere('entity.owner = :owner')->setParameter('owner', $user);
    }

    public function createEntity(string $entityFqcn): Home
    {
        return (new Home())->setOwner($this->currentUser());
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'field.name');
        yield AssociationField::new('rooms', 'menu.rooms')->hideOnForm();
    }
}
