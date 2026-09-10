<?php

use App\Actions\RecordPayment;
use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\Payment;
use App\Models\SitePlan;
use App\Models\Stand;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    $this->branch = Branch::factory()->create(['code' => 'HRE']);
    $this->plan = SitePlan::factory()->for($this->branch)->create();
    $this->buyer = Buyer::factory()->for($this->branch)->create();
    $this->record = app(RecordPayment::class);

    /** A 10,000 stand on a plan: 2,000 deposit and 8 monthly instalments of 1,000. */
    $this->sale = app(SellStand::class)->handle(
        stand: Stand::factory()->for($this->plan)->create([
            'status' => StandStatus::Available,
            'price_cents' => 10_000_00,
            'cost_cents' => 6_000_00,
        ]),
        buyer: $this->buyer,
        type: SaleType::PaymentPlan,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-01-15'),
        method: PaymentMethod::Cash,
        depositCents: 2_000_00,
        instalmentCount: 8,
    );
});

it('moves the receipt from the receivable to the bank', function () {
    $this->record->handle($this->sale, 1_000_00, PaymentMethod::MobileMoney, Carbon::parse('2026-02-15'));

    $entry = Payment::latest('id')->first()->journalEntries()->with('lines.account')->sole();

    expect($entry->lines->pluck('debit_cents', 'account.code')->all())->toBe([Account::BANK => 100_000, Account::ACCOUNTS_RECEIVABLE => 0])
        ->and($entry->lines->pluck('credit_cents', 'account.code')->all())->toBe([Account::BANK => 0, Account::ACCOUNTS_RECEIVABLE => 100_000]);
});

it('numbers receipts sequentially within a branch', function () {
    $this->record->handle($this->sale, 500_00, PaymentMethod::Cash, Carbon::parse('2026-02-15'));
    $this->record->handle($this->sale, 500_00, PaymentMethod::Cash, Carbon::parse('2026-03-15'));

    /** The deposit took the first number when the sale was booked. */
    expect(Payment::orderBy('id')->pluck('receipt_number')->all())
        ->toBe(['RCP-HRE-000001', 'RCP-HRE-000002', 'RCP-HRE-000003']);
});

it('applies a receipt to the oldest unsettled instalment first', function () {
    $this->record->handle($this->sale, 1_500_00, PaymentMethod::Cash, Carbon::parse('2026-02-15'));

    expect($this->sale->instalments()->orderBy('sequence')->pluck('paid_cents')->all())
        ->toBe([100_000, 50_000, 0, 0, 0, 0, 0, 0]);
});

it('settles the sale and the stand once the last of the money is in', function () {
    $this->record->handle($this->sale, 8_000_00, PaymentMethod::BankTransfer, Carbon::parse('2026-02-15'));

    $sale = $this->sale->fresh();

    expect($sale->status)->toBe(SaleStatus::Settled)
        ->and($sale->outstanding_cents)->toBe(0)
        ->and($sale->stand->fresh()->status)->toBe(StandStatus::Sold)
        ->and($sale->instalments()->unsettled()->count())->toBe(0);
});

it('refuses a receipt for more than is still owing', function () {
    $record = fn () => $this->record->handle($this->sale, 8_000_01, PaymentMethod::Cash, Carbon::parse('2026-02-15'));

    expect($record)->toThrow(AccountingException::class, 'exceeds the 8,000.00 still owing');
});

it('refuses a receipt for nothing', function () {
    $record = fn () => $this->record->handle($this->sale, 0, PaymentMethod::Cash, Carbon::today());

    expect($record)->toThrow(AccountingException::class, 'A payment must be for a positive amount.');
});

it('writes no receipt and no entry when the payment is refused', function () {
    $paymentsBefore = Payment::count();

    try {
        $this->record->handle($this->sale, 99_999_00, PaymentMethod::Cash, Carbon::today());
    } catch (AccountingException) {
        // Expected; the point of the test is that nothing was written.
    }

    expect(Payment::count())->toBe($paymentsBefore)
        ->and($this->sale->fresh()->outstanding_cents)->toBe(800_000);
});

it('leaves a cash sale with nothing further to receipt', function () {
    $cashSale = app(SellStand::class)->handle(
        stand: Stand::factory()->for($this->plan)->create([
            'status' => StandStatus::Available,
            'price_cents' => 5_000_00,
            'cost_cents' => 3_000_00,
        ]),
        buyer: $this->buyer,
        type: SaleType::Cash,
        priceCents: 5_000_00,
        saleDate: Carbon::parse('2026-02-01'),
        method: PaymentMethod::Cash,
    );

    $record = fn () => $this->record->handle($cashSale, 1_00, PaymentMethod::Cash, Carbon::parse('2026-02-02'));

    expect($record)->toThrow(AccountingException::class, 'exceeds the 0.00 still owing');
});
