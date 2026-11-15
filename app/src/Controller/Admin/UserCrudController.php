<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<User>
 */
final class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('entity.user.singular')
            ->setEntityLabelInPlural('entity.user.plural')
            ->setSearchFields(['email', 'pseudo'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    /**
     * No "new" button: a password is chosen by its owner, never by the admin.
     * Editing is limited to the roles (giving or removing admin rights is the admin's job).
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'field.id')->hideOnForm();
        // shown for context but not editable: an account's identity belongs to its owner
        yield TextField::new('pseudo', 'field.pseudo')->setDisabled();
        yield EmailField::new('email', 'field.email')->setDisabled();
        yield ChoiceField::new('roles', 'field.roles')
            ->setChoices([
                'role.ROLE_USER' => 'ROLE_USER',
                'role.ROLE_CHILD' => 'ROLE_CHILD',
                'role.ROLE_SUPER_ADMIN' => 'ROLE_SUPER_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderAsBadges();
        // not editable: attaching a child to a parent would let it see the parent's items
        yield AssociationField::new('parent', 'field.parent')->hideOnIndex()->setDisabled();
        yield DateTimeField::new('createdAt', 'field.created_at')->hideOnForm();
        // the password is never displayed nor editable here
    }
}
