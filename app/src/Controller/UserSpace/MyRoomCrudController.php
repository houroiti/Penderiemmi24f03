<?php

namespace App\Controller\UserSpace;

use App\Entity\Room;
use App\Entity\User;
use App\Repository\HomeRepository;
use App\Repository\ItemRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractOwnedCrudController<Room>
 */
final class MyRoomCrudController extends AbstractOwnedCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly HomeRepository $homeRepository,
        private readonly ItemRepository $itemRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Room::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('entity.room.singular')
            ->setEntityLabelInPlural('entity.room.plural');
    }

    public function configureActions(Actions $actions): Actions
    {
        $addFurniture = Action::new('addFurniture', 'action.add_furniture', 'fa fa-plus')
            ->linkToUrl(fn (Room $room): string => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(MyFurnitureCrudController::class)
                ->setAction(Action::NEW)
                ->set('room', $room->getId())
                ->generateUrl());

        $confirmDelete = fn (Action $action): Action => $action->askConfirmation(
            fn (Room $room): TranslatableMessage => new TranslatableMessage('confirm.delete_room', [
                '%name%' => $room->getName(),
                '%furniture%' => $room->getFurniture()->count(),
                '%items%' => $this->itemRepository->countStoredIn($room),
            ]),
        );

        return $actions
            ->add(Crud::PAGE_INDEX, $addFurniture)
            ->add(Crud::PAGE_DETAIL, $addFurniture)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, $confirmDelete)
            ->update(Crud::PAGE_DETAIL, Action::DELETE, $confirmDelete);
    }

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder
            ->join('entity.home', 'ownerHome')
            ->andWhere('ownerHome.owner = :owner')
            ->setParameter('owner', $user);
    }

    /**
     * Coming from "Add a room" on a home: that home is pre-selected, but only if it is the user's.
     */
    public function createEntity(string $entityFqcn): Room
    {
        $room = new Room();
        $homeId = $this->getContext()?->getRequest()->query->getInt('home');
        $home = $homeId > 0 ? $this->homeRepository->find($homeId) : null;

        if (null !== $home && $home->getOwner()?->getId() === $this->currentUser()->getId()) {
            $room->setHome($home);
        }

        return $room;
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->currentUser();

        yield TextField::new('name', 'field.name');
        yield AssociationField::new('home', 'field.home')
            ->setQueryBuilder(static fn (QueryBuilder $queryBuilder) => $queryBuilder
                ->andWhere('entity.owner = :owner')
                ->setParameter('owner', $user));
        yield AssociationField::new('furniture', 'menu.furniture')->hideOnForm();
        if (!$user->isChild()) {
            yield BooleanField::new('isSharedWithCloseFriends', 'field.shared_with_close_friends')->renderAsSwitch(false);
        }
    }
}
