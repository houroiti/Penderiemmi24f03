<?php

namespace App\Controller\Admin;

use App\Entity\Furniture;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Read only for the admin: a user's things are theirs; the admin only looks (KPIs, support).
 *
 * @extends AbstractCrudController<Furniture>
 */
final class FurnitureCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Furniture::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('entity.furniture.singular')
            ->setEntityLabelInPlural('entity.furniture.plural');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'field.name');
        yield AssociationField::new('room', 'field.room');
    }
}
