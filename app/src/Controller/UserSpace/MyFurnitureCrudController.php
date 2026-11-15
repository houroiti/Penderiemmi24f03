<?php

namespace App\Controller\UserSpace;

use App\Entity\Furniture;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractOwnedCrudController<Furniture>
 */
final class MyFurnitureCrudController extends AbstractOwnedCrudController
{
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

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder
            ->join('entity.room', 'ownerRoom')
            ->join('ownerRoom.home', 'ownerHome')
            ->andWhere('ownerHome.owner = :owner')
            ->setParameter('owner', $user);
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->currentUser();

        yield TextField::new('name', 'field.name');
        yield AssociationField::new('room', 'field.room')
            ->setQueryBuilder(static fn (QueryBuilder $queryBuilder) => $queryBuilder
                ->join('entity.home', 'home')
                ->andWhere('home.owner = :owner')
                ->setParameter('owner', $user));
    }
}
