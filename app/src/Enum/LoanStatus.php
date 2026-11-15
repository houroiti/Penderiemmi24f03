<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Never stored: always computed from the loan dates (see Loan::getStatus()).
 */
enum LoanStatus: string implements TranslatableInterface
{
    case InProgress = 'in_progress';
    case Overdue = 'overdue';
    case Returned = 'returned';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('loan_status.'.$this->value, locale: $locale);
    }
}
