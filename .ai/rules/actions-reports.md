---
paths:
  - 'app/{Actions,Reports}/**'
---

# Actions Reports

## Double-entry rules: post through PostJournalEntry, never write journal rows directly
The ledger is the record of the money. Everything else - a sale's status, an instalment's `paid_cents` - is a convenience derived alongside it.

- `App\Actions\PostJournalEntry` is the only thing that writes `journal_entries` / `journal_lines`. It refuses an entry that does not balance, so the trial balance cannot drift. Never insert lines from a controller, a seeder, or a migration.
- Entries are append-only. Correct a mistake by posting a reversing entry; nothing amends or deletes a posted one. There is no cancellation flow yet - build it as reversal, not deletion.
- Revenue recognition is point-in-time at signing (IFRS 15): `SellStand` takes the whole price to revenue and carries the unpaid part as a receivable, and charges the stand's cost out of Land Inventory the same day. `RecordPayment` only moves receivable to bank - it must never touch revenue.
- Money is integer minor units (`*_cents`) everywhere in the database and the reports. Resources divide by 100 at the edge; nothing else does.
- The deposit is deliberately NOT allocated to the instalment schedule (`allocateToSchedule: false`), because the schedule only covers the price after the deposit. Crediting it would run every plan ahead and hide real arrears.
- Statements are derived from the journal on every request - there is no period close and no summary table. Retained earnings is computed as revenue less expenses, not posted.
- The books must satisfy: trial balance debits == credits, assets == liabilities + equity, and receivables ageing total == the Accounts Receivable balance. `tests/Feature/Reports/FinancialStatementsTest.php` asserts all three against hand-worked figures.
