<?php

namespace App\Reports;

use App\Enums\AccountType;
use App\Enums\BalanceSide;
use App\Models\Account;
use App\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Net movement per account, read straight from the journal.
 *
 * Every statement is derived from this, which is what makes them live: there is
 * no period-end close and no summary table to refresh, so a receipt taken a
 * second ago is already in the balance sheet.
 */
class AccountBalances
{
    /**
     * Balances on each account's normal side. A cut-off of `$from` restricts to
     * a period, which is what the income statement needs; leaving it out gives
     * the cumulative position a balance sheet reports.
     *
     * @return Collection<int, object{code: string, name: string, type: AccountType, position: int, balance_cents: int, debit_cents: int, credit_cents: int}>
     */
    public function handle(?Branch $branch, Carbon $to, ?Carbon $from = null): Collection
    {
        /**
         * Totalled in a subquery rather than in the outer join. Filtering the
         * entries inside a LEFT JOIN condition would leave the unwanted lines in
         * the result with a null entry attached, and their amounts would still
         * be summed - which silently reports group totals for every branch.
         */
        $movements = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->when($from, fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $from->toDateString()))
            ->when($branch, fn ($query) => $query->where('journal_entries.branch_id', '=', $branch->id))
            ->groupBy('journal_lines.account_id')
            ->select([
                'journal_lines.account_id',
                DB::raw('SUM(journal_lines.debit_cents) AS debit_cents'),
                DB::raw('SUM(journal_lines.credit_cents) AS credit_cents'),
            ]);

        return Account::query()
            ->inReportOrder()
            ->leftJoinSub($movements, 'movements', 'movements.account_id', '=', 'accounts.id')
            ->select([
                'accounts.code',
                'accounts.name',
                'accounts.type',
                'accounts.position',
                DB::raw('COALESCE(movements.debit_cents, 0) AS debit_cents'),
                DB::raw('COALESCE(movements.credit_cents, 0) AS credit_cents'),
            ])
            ->get()
            ->map(function ($row): object {
                $type = $row->type instanceof AccountType ? $row->type : AccountType::from($row->type);
                $debit = (int) $row->debit_cents;
                $credit = (int) $row->credit_cents;

                return (object) [
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $type,
                    'position' => (int) $row->position,
                    'debit_cents' => $debit,
                    'credit_cents' => $credit,
                    /** Positive means the account sits on its normal side. */
                    'balance_cents' => $type->normalBalance() === BalanceSide::Debit
                        ? $debit - $credit
                        : $credit - $debit,
                ];
            });
    }
}
