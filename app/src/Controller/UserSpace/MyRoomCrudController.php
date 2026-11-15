<?php

namespace App\Controller\UserSpace;

use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractOwnedCrudController<Room>
 */
final class MyRoomCrudController extends AbstractOwnedCrudController
{
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

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder
            ->join('entity.home', 'ownerHome')
            ->andWhere('ownerHome.owner = :owner')
            ->setParameter('owner', $user);
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->currentUser();

        yield TextField::new('name', 'field.name');
        yield AssociationField::new('home', 'field.home')
            ->setQueryBuilder(static fn (QueryBuilder $queryBuilder) => $queryBuilder
                ->andWhere('entity.owner = :owner')
                ->setParameter('owner', $user));
        if (!$user->isChild()) {
            yield BooleanField::new('isSharedWithCloseFriends', 'field.shared_with_close_friends')->renderAsSwitch(false);
        }
    }
}
