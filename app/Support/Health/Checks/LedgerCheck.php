<?php

namespace App\Support\Health\Checks;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Throwable;

/**
 * The one invariant the books cannot survive losing: total debits equal total
 * credits across every branch.
 *
 * `PostJournalEntry` refuses an entry that does not balance, so this can only
 * come out false if something wrote to `journal_lines` behind its back - a
 * migration, a seeder, or a hand-run UPDATE. Every statement is derived from
 * these rows, so the figures stop being trustworthy the moment it does.
 *
 * It scans the ledger, so it is left out of readiness and reported by the
 * diagnostics endpoint only.
 */
final class LedgerCheck implements HealthCheck
{
    public function name(): string
    {
        return 'ledger';
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function run(): CheckResult
    {
        try {
            $totals = JournalLine::query()
                ->selectRaw('coalesce(sum(debit_cents), 0) as debits, coalesce(sum(credit_cents), 0) as credits')
                ->first();

            $entries = JournalEntry::query()->count();
        } catch (Throwable $exception) {
            return CheckResult::failed($this->name(), 'The ledger could not be read.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $debits = (int) $totals->debits;
        $credits = (int) $totals->credits;

        $detail = [
            'entries' => $entries,
            'totalDebitCents' => $debits,
            'totalCreditCents' => $credits,
            'differenceCents' => $debits - $credits,
        ];

        if ($debits !== $credits) {
            return CheckResult::failed(
                $this->name(),
                'The ledger does not balance: something has written journal lines outside the posting action.',
                $detail,
            );
        }

        return CheckResult::ok($this->name(), sprintf('%d journal entries balance.', $entries), $detail);
    }
}
