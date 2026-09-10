<?php

namespace App\Reports;

use App\Enums\AccountType;
use App\Models\Branch;
use Illuminate\Support\Carbon;

/**
 * Revenue less cost of sales for a period.
 *
 * Because revenue is recognised when the stand is sold rather than when the
 * money arrives, this reports the margin earned in the period, not the cash
 * collected in it.
 */
class IncomeStatement
{
    public function __construct(private AccountBalances $balances) {}

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     branch: ?string,
     *     revenue: list<array{code: string, name: string, amountCents: int}>,
     *     expenses: list<array{code: string, name: string, amountCents: int}>,
     *     revenueCents: int,
     *     expensesCents: int,
     *     netIncomeCents: int
     * }
     */
    public function handle(?Branch $branch, Carbon $from, Carbon $to): array
    {
        $balances = $this->balances->handle($branch, $to, $from);

        $section = fn (AccountType $type) => $balances
            ->filter(fn (object $account): bool => $account->type === $type)
            ->map(fn (object $account): array => [
                'code' => $account->code,
                'name' => $account->name,
                'amountCents' => $account->balance_cents,
            ])
            ->values();

        $revenue = $section(AccountType::Revenue);
        $expenses = $section(AccountType::Expense);

        $revenueCents = (int) $revenue->sum('amountCents');
        $expensesCents = (int) $expenses->sum('amountCents');

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branch' => $branch?->code,
            'revenue' => $revenue->all(),
            'expenses' => $expenses->all(),
            'revenueCents' => $revenueCents,
            'expensesCents' => $expensesCents,
            'netIncomeCents' => $revenueCents - $expensesCents,
        ];
    }
}
