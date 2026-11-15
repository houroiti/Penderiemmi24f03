<?php

namespace App\Controller\Admin;

use App\Entity\Item;
use App\Enum\ItemCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * The admin can see every item and delete one for moderation (inappropriate content),
 * but never create or change one: unticking "personal" or opening it to followers
 * would expose it without its owner's consent.
 *
 * @extends AbstractCrudController<Item>
 */
final class ItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Item::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('entity.item.singular')
            ->setEntityLabelInPlural('entity.item.plural')
            ->setSearchFields(['name', 'brand', 'color'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('owner')->add('category')->add('isPersonal')->add('color')->add('brand');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'field.name');
        yield AssociationField::new('owner', 'field.owner');
        yield ChoiceField::new('category', 'field.category')->setChoices(ItemCategory::choices());
        yield TextField::new('color', 'field.color');
        yield TextField::new('brand', 'field.brand');
        yield AssociationField::new('furniture', 'field.furniture')->hideOnIndex();
        yield BooleanField::new('isPersonal', 'field.is_personal')->renderAsSwitch(false);
        yield BooleanField::new('isVisibleToFollowers', 'field.visible_to_followers')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'field.created_at')->hideOnForm();
    }
}
