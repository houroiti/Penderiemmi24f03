<?php

namespace App\Controller;

use App\Enum\LoanStatus;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

use function Symfony\Component\Translation\t;

/**
 * The loan status is not a column: it is computed by Loan::getStatus() and shown as a coloured badge.
 */
trait LoanStatusFieldTrait
{
    private function loanStatusField(): ChoiceField
    {
        $choices = [];
        foreach (LoanStatus::cases() as $status) {
            $choices[$status->value] = t('loan_status.'.$status->value);
        }

        return ChoiceField::new('status', 'field.status')
            ->onlyOnIndex()
            ->setTranslatableChoices($choices)
            ->renderAsBadges([
                LoanStatus::InProgress->value => 'info',
                LoanStatus::Overdue->value => 'danger',
                LoanStatus::Returned->value => 'success',
            ]);
    }
}
