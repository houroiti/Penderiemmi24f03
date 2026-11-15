<?php

namespace App\Controller\Admin;

use App\Controller\LoanStatusFieldTrait;
use App\Entity\Loan;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
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
