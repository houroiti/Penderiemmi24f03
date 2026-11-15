<?php

namespace App\Controller\Admin;

use App\Entity\Follow;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

/**
 * Moderation only: the admin can see and delete a relation (abuse, fake account...),
 * but never create or change one. Deleting can only remove an access; creating one
 * would expose someone's things without their consent: that decision belongs to the user.
 *
 * @extends AbstractCrudController<Follow>
 */
final class FollowCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Follow::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('entity.follow.singular')
            ->setEntityLabelInPlural('entity.follow.plural')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('followed')->add('isCloseFriend');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('follower', 'field.follower');
        yield AssociationField::new('followed', 'field.followed');
        yield BooleanField::new('isCloseFriend', 'field.close_friend')->setHelp('help.close_friend');
        yield DateTimeField::new('createdAt', 'field.created_at')->hideOnForm();
    }
}
