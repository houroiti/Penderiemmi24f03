<?php

namespace App\Controller\UserSpace;

use App\Entity\User;
use App\Security\Voter\OwnedVoter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Base of every screen of "Mon espace". Two locks, against "stealing the neighbour's things":
 *  1. the list only contains the current user's rows (filtered in SQL);
 *  2. showing, editing or deleting one row asks OwnedVoter, so changing the id in the URL is refused.
 *
 * @template TEntity of object
 *
 * @extends AbstractCrudController<TEntity>
 */
abstract class AbstractOwnedCrudController extends AbstractCrudController
{
    /**
     * Adds the "belongs to $user" condition to the query (root alias: "entity").
     */
    abstract protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void;

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission(OwnedVoter::MANAGE);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $this->restrictToOwner($queryBuilder, $this->currentUser());

        return $queryBuilder;
    }

    protected function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $user;
    }
}
