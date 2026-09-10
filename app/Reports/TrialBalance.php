<?php

namespace App\Reports;

use App\Enums\BalanceSide;
use App\Models\Branch;
use Illuminate\Support\Carbon;

/**
 * Every account with its net debit or credit, and the proof that the two sides
 * agree. If `inBalance` is ever false the ledger has been written to by
 * something other than App\Actions\PostJournalEntry.
 */
class TrialBalance
{
    public function __construct(private AccountBalances $balances) {}

    /**
     * @return array{
     *     asAt: string,
     *     branch: ?string,
     *     rows: list<array{code: string, name: string, type: string, debitCents: int, creditCents: int}>,
     *     totalDebitCents: int,
     *     totalCreditCents: int,
     *     inBalance: bool
     * }
     */
    public function handle(?Branch $branch, Carbon $asAt): array
    {
        $rows = $this->balances->handle($branch, $asAt)
            ->filter(fn (object $account): bool => $account->debit_cents !== 0 || $account->credit_cents !== 0)
            ->map(function (object $account): array {
                $onDebitSide = $account->type->normalBalance() === BalanceSide::Debit;
                $net = $account->balance_cents;

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    /** A negative balance is shown on the opposite side rather than as a minus. */
                    'debitCents' => $onDebitSide ? max(0, $net) : max(0, -$net),
                    'creditCents' => $onDebitSide ? max(0, -$net) : max(0, $net),
                ];
            })
            ->values();

        $totalDebit = $rows->sum('debitCents');
        $totalCredit = $rows->sum('creditCents');

        return [
            'asAt' => $asAt->toDateString(),
            'branch' => $branch?->code,
            'rows' => $rows->all(),
            'totalDebitCents' => $totalDebit,
            'totalCreditCents' => $totalCredit,
            'inBalance' => $totalDebit === $totalCredit,
        ];
    }
}
