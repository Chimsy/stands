<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Sale;
use App\Models\Stand;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\LedgerLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sells a stand, on cash terms or on a payment plan.
 *
 * Control of the stand passes to the buyer at signing, so the whole price is
 * recognised as revenue on the day of sale and the unpaid part is carried as a
 * receivable. The stand also leaves inventory at its carrying cost on the same
 * day, so the margin lands in the period the sale happened:
 *
 *   Dr Accounts Receivable      price
 *       Cr Stand Sales Revenue      price
 *   Dr Cost of Sales             cost
 *       Cr Land Inventory            cost
 *
 * The deposit, or the full price on a cash sale, is then receipted through
 * RecordPayment, which is what clears the receivable and issues the receipt.
 */
class SellStand
{
    public function __construct(
        private PostJournalEntry $postJournalEntry,
        private RecordPayment $recordPayment,
    ) {}

    /**
     * @throws AccountingException
     */
    public function handle(
        Stand $stand,
        Buyer $buyer,
        SaleType $type,
        int $priceCents,
        Carbon $saleDate,
        PaymentMethod $method,
        ?User $soldBy = null,
        int $depositCents = 0,
        int $instalmentCount = 0,
    ): Sale {
        $this->guard($stand, $buyer, $type, $priceCents, $depositCents, $instalmentCount);

        return DB::transaction(function () use (
            $stand, $buyer, $type, $priceCents, $saleDate, $method, $soldBy, $depositCents, $instalmentCount
        ): Sale {
            $branch = $stand->sitePlan->branch;
            $settledUpFront = $type === SaleType::Cash;

            $sale = Sale::create([
                'branch_id' => $branch->id,
                'stand_id' => $stand->id,
                'buyer_id' => $buyer->id,
                'sold_by' => $soldBy?->id,
                'reference' => DocumentNumber::next('SALE', $branch, 'sales', 'reference'),
                'sale_date' => $saleDate,
                'type' => $type,
                'status' => SaleStatus::Outstanding,
                'price_cents' => $priceCents,
                'cost_cents' => $stand->cost_cents,
                'deposit_cents' => $settledUpFront ? $priceCents : $depositCents,
                'instalment_count' => $settledUpFront ? 0 : $instalmentCount,
            ]);

            if (! $settledUpFront) {
                $sale->instalments()->createMany(
                    $this->schedule($priceCents - $depositCents, $instalmentCount, $saleDate),
                );
            }

            $lines = [
                LedgerLine::debit(Account::ACCOUNTS_RECEIVABLE, $priceCents),
                LedgerLine::credit(Account::STAND_SALES_REVENUE, $priceCents),
            ];

            /** A stand carried at nothing produces no cost of sales to post. */
            if ($stand->cost_cents > 0) {
                $lines[] = LedgerLine::debit(Account::COST_OF_SALES, $stand->cost_cents);
                $lines[] = LedgerLine::credit(Account::LAND_INVENTORY, $stand->cost_cents);
            }

            $this->postJournalEntry->handle(
                branch: $branch,
                date: $saleDate,
                description: sprintf('Sale %s of stand %s to %s', $sale->reference, $stand->stand_number, $buyer->name),
                lines: $lines,
                source: $sale,
                recordedBy: $soldBy,
            );

            /** A plan is in progress until the last instalment lands; a cash sale is done today. */
            $stand->update(['status' => $settledUpFront ? StandStatus::Sold : StandStatus::InProgress]);

            $upFront = $settledUpFront ? $priceCents : $depositCents;

            if ($upFront > 0) {
                $this->recordPayment->handle(
                    sale: $sale,
                    amountCents: $upFront,
                    method: $method,
                    paidOn: $saleDate,
                    receivedBy: $soldBy,
                    /** The schedule already excludes the deposit, so it must not absorb it. */
                    allocateToSchedule: $settledUpFront,
                );
            }

            return $sale->fresh();
        });
    }

    /**
     * Equal monthly instalments, with any rounding remainder pushed into the
     * final one so the schedule sums to the balance exactly.
     *
     * @return list<array{sequence: int, due_date: Carbon, amount_cents: int}>
     */
    private function schedule(int $balanceCents, int $count, Carbon $saleDate): array
    {
        $base = intdiv($balanceCents, $count);
        $remainder = $balanceCents - ($base * $count);

        return array_map(fn (int $sequence): array => [
            'sequence' => $sequence,
            'due_date' => $saleDate->copy()->addMonthsNoOverflow($sequence),
            'amount_cents' => $sequence === $count ? $base + $remainder : $base,
        ], range(1, $count));
    }

    /**
     * @throws AccountingException
     */
    private function guard(
        Stand $stand,
        Buyer $buyer,
        SaleType $type,
        int $priceCents,
        int $depositCents,
        int $instalmentCount,
    ): void {
        if ($stand->status !== StandStatus::Available) {
            throw new AccountingException("Stand {$stand->stand_number} is not available for sale.");
        }

        if ($priceCents <= 0) {
            throw new AccountingException('A sale needs a positive selling price.');
        }

        if ($buyer->branch_id !== $stand->sitePlan->branch_id) {
            throw new AccountingException('The buyer is registered at a different branch to the stand.');
        }

        if ($type === SaleType::Cash) {
            return;
        }

        if ($instalmentCount < 1) {
            throw new AccountingException('A payment plan needs at least one instalment.');
        }

        if ($depositCents < 0 || $depositCents >= $priceCents) {
            throw new AccountingException('The deposit must be less than the selling price.');
        }
    }
}
