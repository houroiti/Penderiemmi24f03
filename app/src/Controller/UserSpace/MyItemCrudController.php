<?php

namespace App\Controller\UserSpace;

use App\Entity\Item;
use App\Entity\User;
use App\Enum\ItemCategory;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractOwnedCrudController<Item>
 */
final class MyItemCrudController extends AbstractOwnedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Item::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('entity.item.singular')
            ->setEntityLabelInPlural('entity.item.plural')
            ->setSearchFields(['name', 'brand', 'color'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('category')->add('isPersonal')->add('furniture')->add('color')->add('brand');
    }

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder->andWhere('entity.owner = :owner')->setParameter('owner', $user);
    }

    /**
     * The owner is never a form field: it is always the logged-in user.
     */
    public function createEntity(string $entityFqcn): Item
    {
        return (new Item())->setOwner($this->currentUser());
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->currentUser();

        yield TextField::new('name', 'field.name');
        yield ChoiceField::new('category', 'field.category')->setChoices(ItemCategory::choices());
        yield TextField::new('color', 'field.color');
        yield TextField::new('brand', 'field.brand');
        // only the user's own furniture can be chosen
        yield AssociationField::new('furniture', 'field.furniture')
            ->setQueryBuilder(static fn (QueryBuilder $queryBuilder) => $queryBuilder
                ->join('entity.room', 'room')
                ->join('room.home', 'home')
                ->andWhere('home.owner = :owner')
                ->setParameter('owner', $user));
        yield BooleanField::new('isPersonal', 'field.is_personal')->renderAsSwitch(false)->setHelp('help.is_personal');
        // a child sub-account cannot share
        if (!$user->isChild()) {
            yield BooleanField::new('isVisibleToFollowers', 'field.visible_to_followers')->hideOnIndex()->setHelp('help.visible_to_followers');
        }
        yield DateTimeField::new('createdAt', 'field.created_at')->hideOnForm();
    }
}
