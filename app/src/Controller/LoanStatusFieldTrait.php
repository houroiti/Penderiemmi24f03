<?php

namespace App\Controller;

use App\Entity\Loan;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The loan status is not a column: it is computed by Loan::getStatus() and translated for display.
 */
trait LoanStatusFieldTrait
{
    private function loanStatusField(TranslatorInterface $translator): TextField
    {
        return TextField::new('status', 'field.status')
            ->onlyOnIndex()
            ->formatValue(static fn (mixed $value, Loan $loan): string => $loan->getStatus()->trans($translator));
    }
}
