<?php

namespace App\Controller\Admin;

use App\Controller\LoanStatusFieldTrait;
use App\Entity\Loan;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Read only for the admin: a loan is a private record between the owner and the borrower;
 * it grants no access, so there is nothing to moderate.
 *
 * @extends AbstractCrudController<Loan>
 */
final class LoanCrudController extends AbstractCrudController
{
    use LoanStatusFieldTrait;

    public static function getEntityFqcn(): string
    {
        return Loan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('entity.loan.singular')
            ->setEntityLabelInPlural('entity.loan.plural')
            ->setDefaultSort(['lentAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('item', 'field.item');
        yield TextField::new('borrowerName', 'field.borrower');
        yield DateField::new('lentAt', 'field.lent_at');
        yield DateField::new('dueDate', 'field.due_date');
        yield DateField::new('returnedAt', 'field.returned_at');
        yield $this->loanStatusField();
    }
}
