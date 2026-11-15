<?php

namespace App\Controller\UserSpace;

use App\Entity\Furniture;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractOwnedCrudController<Furniture>
 */
final class MyFurnitureCrudController extends AbstractOwnedCrudController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly ItemRepository $itemRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Furniture::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('entity.furniture.singular')
            ->setEntityLabelInPlural('entity.furniture.plural');
    }

    public function configureActions(Actions $actions): Actions
    {
        $confirmDelete = fn (Action $action): Action => $action->askConfirmation(
            fn (Furniture $furniture): TranslatableMessage => new TranslatableMessage('confirm.delete_furniture', [
                '%name%' => $furniture->getName(),
                '%items%' => $this->itemRepository->countStoredIn($furniture),
            ]),
        );

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, $confirmDelete)
            ->update(Crud::PAGE_DETAIL, Action::DELETE, $confirmDelete);
    }

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder
            ->join('entity.room', 'ownerRoom')
            ->join('ownerRoom.home', 'ownerHome')
            ->andWhere('ownerHome.owner = :owner')
            ->setParameter('owner', $user);
    }

    /**
     * Coming from "Add a piece of furniture" on a room: that room is pre-selected, but only if it is the user's.
     */
    public function createEntity(string $entityFqcn): Furniture
    {
        $furniture = new Furniture();
        $roomId = $this->getContext()?->getRequest()->query->getInt('room');
        $room = $roomId > 0 ? $this->roomRepository->find($roomId) : null;

        if (null !== $room && $room->getOwner()?->getId() === $this->currentUser()->getId()) {
            $furniture->setRoom($room);
        }

        return $furniture;
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->currentUser();

        yield TextField::new('name', 'field.name');
        yield AssociationField::new('room', 'field.room')
            // an empty first choice: nothing is silently pre-selected, the user chooses on purpose
            ->setFormTypeOption('placeholder', 'form.choose_room')
            // the search widget of EasyAdmin displays this attribute (otherwise "Aucun(e)")
            ->setFormTypeOption('attr.placeholder', t('form.choose_room'))
            ->setQueryBuilder(static fn (QueryBuilder $queryBuilder) => $queryBuilder
                ->join('entity.home', 'home')
                ->andWhere('home.owner = :owner')
                ->setParameter('owner', $user));
    }
}
