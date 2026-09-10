<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StandStatus;
use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\LedgerLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Receives money against a sale and issues the receipt for it.
 *
 * The posting is the settlement half of the sale: the debt raised when the
 * stand was sold is reduced and the bank goes up. No revenue is recognised
 * here - that already happened on the day of sale.
 *
 * Allocation to the instalment schedule is separate from the posting. The
 * ledger is right either way; the allocation is only what lets arrears and
 * ageing say which instalment is short.
 *
 *   Dr Bank
 *       Cr Accounts Receivable
 */
class RecordPayment
{
    public function __construct(private PostJournalEntry $postJournalEntry) {}

    /**
     * @throws AccountingException
     */
    public function handle(
        Sale $sale,
        int $amountCents,
        PaymentMethod $method,
        Carbon $paidOn,
        ?User $receivedBy = null,
        ?string $externalReference = null,
        bool $allocateToSchedule = true,
    ): Payment {
        if ($amountCents <= 0) {
            throw new AccountingException('A payment must be for a positive amount.');
        }

        return DB::transaction(function () use ($sale, $amountCents, $method, $paidOn, $receivedBy, $externalReference, $allocateToSchedule): Payment {
            /** Re-read under the transaction so two receipts cannot both see the same balance. */
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($amountCents > $sale->outstanding_cents) {
                throw new AccountingException(sprintf(
                    'Payment of %s exceeds the %s still owing on %s.',
                    number_format($amountCents / 100, 2),
                    number_format($sale->outstanding_cents / 100, 2),
                    $sale->reference,
                ));
            }

            $payment = Payment::create([
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'received_by' => $receivedBy?->id,
                'receipt_number' => DocumentNumber::next('RCP', $sale->branch, 'payments', 'receipt_number'),
                'paid_on' => $paidOn,
                'amount_cents' => $amountCents,
                'method' => $method,
                'external_reference' => $externalReference,
            ]);

            if ($allocateToSchedule) {
                $this->allocateToInstalments($sale, $amountCents);
            }

            $this->postJournalEntry->handle(
                branch: $sale->branch,
                date: $paidOn,
                description: sprintf('Receipt %s for stand %s', $payment->receipt_number, $sale->stand->stand_number),
                lines: [
                    LedgerLine::debit(Account::BANK, $amountCents),
                    LedgerLine::credit(Account::ACCOUNTS_RECEIVABLE, $amountCents),
                ],
                source: $payment,
                recordedBy: $receivedBy,
            );

            $this->settleIfPaidInFull($sale);

            return $payment->fresh();
        });
    }

    /**
     * Applies the receipt to the schedule oldest instalment first, which is how
     * the arrears and ageing reports know which instalment is still short.
     *
     * The deposit is the one payment that is not allocated: the schedule only
     * ever covers the price after the deposit, so crediting it here would run
     * the whole plan ahead and hide genuine arrears.
     */
    private function allocateToInstalments(Sale $sale, int $amountCents): void
    {
        $remaining = $amountCents;

        $instalments = $sale->instalments()->unsettled()->orderBy('sequence')->get();

        foreach ($instalments as $instalment) {
            if ($remaining <= 0) {
                break;
            }

            $applied = min($remaining, $instalment->outstanding_cents);
            $instalment->update(['paid_cents' => $instalment->paid_cents + $applied]);
            $remaining -= $applied;
        }
    }

    private function settleIfPaidInFull(Sale $sale): void
    {
        if ($sale->fresh()->outstanding_cents > 0) {
            return;
        }

        $sale->update(['status' => SaleStatus::Settled]);
        $sale->stand()->update(['status' => StandStatus::Sold]);
    }
}
