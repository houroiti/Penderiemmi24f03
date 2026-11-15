<?php

namespace App\Controller\Admin;

use App\Entity\Follow;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

/**
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
