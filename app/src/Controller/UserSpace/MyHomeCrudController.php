<?php

namespace App\Controller\UserSpace;

use App\Entity\Home;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractOwnedCrudController<Home>
 */
final class MyHomeCrudController extends AbstractOwnedCrudController
{
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
        yield AssociationField::new('rooms', 'menu.rooms')->onlyOnIndex();
    }
}
