<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The admin manages accounts: give or remove admin rights, delete an account (moderation).
 * Never create one (a password is chosen by its owner), never change someone's identity,
 * child status or parent (that would let the child see another parent's items).
 *
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

    public function configureActions(Actions $actions): Actions
    {
        // an admin cannot delete their own account: they would lock themselves out
        $notMyself = fn (User $user): bool => !$this->isCurrentUser($user);

        return $actions
            ->disable(Action::NEW, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action) => $action->displayIf($notMyself))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn (Action $action) => $action->displayIf($notMyself));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'field.id')->hideOnForm();
        // shown for context but not editable: an account's identity belongs to its owner
        yield TextField::new('pseudo', 'field.pseudo')->setDisabled();
        yield EmailField::new('email', 'field.email')->setDisabled();
        // all the roles are displayed...
        yield ChoiceField::new('roles', 'field.roles')
            ->setChoices([
                'role.ROLE_USER' => 'ROLE_USER',
                'role.ROLE_CHILD' => 'ROLE_CHILD',
                'role.ROLE_SUPER_ADMIN' => 'ROLE_SUPER_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderAsBadges()
            ->hideOnForm();
        // ...but only the admin right can be changed, and never on one's own account
        $superAdmin = BooleanField::new('superAdmin', 'role.ROLE_SUPER_ADMIN')->onlyOnForms();
        $edited = $this->getContext()?->getEntity()->getInstance();
        if ($edited instanceof User && $this->isCurrentUser($edited)) {
            $superAdmin->setDisabled();
        }
        yield $superAdmin;
        // not editable: attaching a child to a parent would let it see the parent's items
        yield AssociationField::new('parent', 'field.parent')->hideOnIndex()->setDisabled();
        yield DateTimeField::new('createdAt', 'field.created_at')->hideOnForm();
        // the password is never displayed nor editable here
    }

    /**
     * Server-side safety net, in case the request is forged: an admin keeps their own admin right.
     *
     * @param User $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($this->isCurrentUser($entityInstance)) {
            $entityInstance->setSuperAdmin(true);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * @param User $entityInstance
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($this->isCurrentUser($entityInstance)) {
            throw new AccessDeniedException('An admin cannot delete their own account.');
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    private function isCurrentUser(User $user): bool
    {
        $current = $this->getUser();

        return $current instanceof User && null !== $user->getId() && $current->getId() === $user->getId();
    }
}
