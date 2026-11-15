<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Loan;
use App\Enum\LoanStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The status of a loan is never stored: it is deduced from its dates.
 */
final class LoanTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testStatusIsDeducedFromDates(?string $dueDate, ?string $returnedAt, LoanStatus $expected): void
    {
        $loan = (new Loan())
            ->setLentAt(new \DateTimeImmutable('2026-11-01'))
            ->setDueDate(null === $dueDate ? null : new \DateTimeImmutable($dueDate))
            ->setReturnedAt(null === $returnedAt ? null : new \DateTimeImmutable($returnedAt));

        self::assertSame($expected, $loan->getStatus(new \DateTimeImmutable('2026-11-15')));
    }

    /**
     * @return iterable<string, array{?string, ?string, LoanStatus}>
     */
    public static function statusProvider(): iterable
    {
        yield 'no due date, not returned' => [null, null, LoanStatus::InProgress];
        yield 'due date in the future' => ['2026-11-20', null, LoanStatus::InProgress];
        yield 'due today is not late yet' => ['2026-11-15', null, LoanStatus::InProgress];
        yield 'due date passed' => ['2026-11-10', null, LoanStatus::Overdue];
        yield 'returned on time' => ['2026-11-20', '2026-11-12', LoanStatus::Returned];
        yield 'returned late is still returned' => ['2026-11-10', '2026-11-14', LoanStatus::Returned];
    }
}
