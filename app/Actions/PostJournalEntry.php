<?php

namespace App\Actions;

use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\LedgerLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only way an entry reaches the ledger.
 *
 * Every entry is checked to balance before it is written, so the trial balance
 * cannot drift: if debits and credits ever disagree, the transaction that
 * caused it is rolled back rather than recorded.
 */
class PostJournalEntry
{
    /**
     * @param  list<LedgerLine>  $lines
     *
     * @throws AccountingException
     */
    public function handle(
        Branch $branch,
        Carbon $date,
        string $description,
        array $lines,
        ?Model $source = null,
        ?User $recordedBy = null,
    ): JournalEntry {
        if (count($lines) < 2) {
            throw AccountingException::emptyEntry();
        }

        $debits = array_sum(array_map(fn (LedgerLine $line): int => $line->debitCents, $lines));
        $credits = array_sum(array_map(fn (LedgerLine $line): int => $line->creditCents, $lines));

        if ($debits !== $credits) {
            throw AccountingException::unbalanced($debits, $credits);
        }

        if ($debits === 0) {
            throw AccountingException::zeroValueEntry();
        }

        return DB::transaction(function () use ($branch, $date, $description, $lines, $source, $recordedBy): JournalEntry {
            $entry = JournalEntry::create([
                'branch_id' => $branch->id,
                'reference' => DocumentNumber::next('JE', $branch, 'journal_entries', 'reference'),
                'entry_date' => $date,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'recorded_by' => $recordedBy?->id,
            ]);

            $accountIds = Account::query()
                ->whereIn('code', array_map(fn (LedgerLine $line): string => $line->accountCode, $lines))
                ->pluck('id', 'code');

            $entry->lines()->createMany(array_map(fn (LedgerLine $line): array => [
                'account_id' => $accountIds[$line->accountCode],
                'debit_cents' => $line->debitCents,
                'credit_cents' => $line->creditCents,
            ], $lines));

            return $entry;
        });
    }
}
