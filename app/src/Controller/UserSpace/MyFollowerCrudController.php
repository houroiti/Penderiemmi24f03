<?php

namespace App\Controller\UserSpace;

use App\Entity\Follow;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The people who follow me: I decide who is a close friend, or I remove a follower.
 * Not available to a child sub-account, which cannot share.
 *
 * @extends AbstractOwnedCrudController<Follow>
 */
#[IsGranted(new Expression('not is_granted("ROLE_CHILD")'))]
final class MyFollowerCrudController extends AbstractOwnedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Follow::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('entity.follow.singular')
            ->setEntityLabelInPlural('entity.follow.plural')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW);
    }

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder->andWhere('entity.followed = :owner')->setParameter('owner', $user);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('follower', 'field.follower')->setDisabled();
        yield BooleanField::new('isCloseFriend', 'field.close_friend')->setHelp('help.close_friend');
        yield DateTimeField::new('createdAt', 'field.created_at')->hideOnForm();
    }
}
