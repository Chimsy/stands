<?php

namespace App\Reports;

use App\Enums\AccountType;
use App\Models\Branch;
use Illuminate\Support\Carbon;

/**
 * The position as at a date.
 *
 * There is no period close in this system, so retained earnings is not a posted
 * account: it is derived from every revenue and expense entry up to the cut-off
 * and shown within equity. That is what keeps the sheet balanced without a
 * closing journal having to be run first.
 */
class BalanceSheet
{
    public function __construct(private AccountBalances $balances) {}

    /**
     * @return array{
     *     asAt: string,
     *     branch: ?string,
     *     assets: list<array{code: string, name: string, amountCents: int}>,
     *     liabilities: list<array{code: string, name: string, amountCents: int}>,
     *     equity: list<array{code: string, name: string, amountCents: int}>,
     *     assetsCents: int,
     *     liabilitiesCents: int,
     *     equityCents: int,
     *     retainedEarningsCents: int,
     *     inBalance: bool
     * }
     */
    public function handle(?Branch $branch, Carbon $asAt): array
    {
        $balances = $this->balances->handle($branch, $asAt);

        $section = fn (AccountType $type) => $balances
            ->filter(fn (object $account): bool => $account->type === $type && $account->balance_cents !== 0)
            ->map(fn (object $account): array => [
                'code' => $account->code,
                'name' => $account->name,
                'amountCents' => $account->balance_cents,
            ])
            ->values();

        $sum = fn (AccountType $type): int => (int) $balances
            ->filter(fn (object $account): bool => $account->type === $type)
            ->sum('balance_cents');

        $retainedEarnings = $sum(AccountType::Revenue) - $sum(AccountType::Expense);

        $equity = $section(AccountType::Equity);

        if ($retainedEarnings !== 0) {
            $equity->push([
                'code' => '3900',
                'name' => 'Retained Earnings',
                'amountCents' => $retainedEarnings,
            ]);
        }

        $assetsCents = $sum(AccountType::Asset);
        $liabilitiesCents = $sum(AccountType::Liability);
        $equityCents = $sum(AccountType::Equity) + $retainedEarnings;

        return [
            'asAt' => $asAt->toDateString(),
            'branch' => $branch?->code,
            'assets' => $section(AccountType::Asset)->all(),
            'liabilities' => $section(AccountType::Liability)->all(),
            'equity' => $equity->all(),
            'assetsCents' => $assetsCents,
            'liabilitiesCents' => $liabilitiesCents,
            'equityCents' => $equityCents,
            'retainedEarningsCents' => $retainedEarnings,
            'inBalance' => $assetsCents === $liabilitiesCents + $equityCents,
        ];
    }
}
