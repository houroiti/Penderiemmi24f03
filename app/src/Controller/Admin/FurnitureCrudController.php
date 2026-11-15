<?php

namespace App\Controller\Admin;

use App\Entity\Furniture;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
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

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'field.name');
        yield AssociationField::new('room', 'field.room');
    }
}
