<?php

namespace App\Controller\UserSpace;

use App\Controller\LoanStatusFieldTrait;
use App\Entity\Loan;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractOwnedCrudController<Loan>
 */
final class MyLoanCrudController extends AbstractOwnedCrudController
{
    use LoanStatusFieldTrait;

    public static function getEntityFqcn(): string
    {
        return Loan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('entity.loan.singular')
            ->setEntityLabelInPlural('entity.loan.plural')
            ->setDefaultSort(['lentAt' => 'DESC']);
    }

    protected function restrictToOwner(QueryBuilder $queryBuilder, User $user): void
    {
        $queryBuilder
            ->join('entity.item', 'ownerItem')
            ->andWhere('ownerItem.owner = :owner')
            ->setParameter('owner', $user);
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->currentUser();

        // only the user's own items can be lent
        yield AssociationField::new('item', 'field.item')
            // an empty first choice: nothing is silently pre-selected, the user chooses on purpose
            ->setFormTypeOption('placeholder', 'form.choose_item')
            // the search widget of EasyAdmin displays this attribute (otherwise "Aucun(e)")
            ->setFormTypeOption('attr.placeholder', t('form.choose_item'))
            ->setQueryBuilder(static fn (QueryBuilder $queryBuilder) => $queryBuilder
                ->andWhere('entity.owner = :owner')
                ->setParameter('owner', $user));
        yield TextField::new('borrowerName', 'field.borrower');
        yield DateField::new('lentAt', 'field.lent_at');
        yield DateField::new('dueDate', 'field.due_date');
        yield DateField::new('returnedAt', 'field.returned_at');
        yield $this->loanStatusField();
    }
}
