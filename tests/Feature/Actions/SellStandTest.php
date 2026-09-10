<?php

use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\JournalEntry;
use App\Models\SitePlan;
use App\Models\Stand;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    $this->branch = Branch::factory()->create(['code' => 'HRE']);
    $this->plan = SitePlan::factory()->for($this->branch)->create();
    $this->buyer = Buyer::factory()->for($this->branch)->create(['name' => 'Tendai Moyo']);
    $this->sell = app(SellStand::class);

    $this->stand = fn (array $attributes = []) => Stand::factory()->for($this->plan)->create([
        'status' => StandStatus::Available,
        'price_cents' => 10_000_00,
        'cost_cents' => 6_000_00,
        ...$attributes,
    ]);
});

/**
 * @return array<string, int> account code => net debit in cents
 */
function netByAccount(): array
{
    return JournalEntry::with('lines.account')
        ->get()
        ->flatMap->lines
        ->groupBy(fn ($line) => $line->account->code)
        ->map(fn ($lines) => $lines->sum('debit_cents') - $lines->sum('credit_cents'))
        ->all();
}

it('recognises the whole price as revenue on the day of a cash sale', function () {
    $sale = $this->sell->handle(
        stand: ($this->stand)(),
        buyer: $this->buyer,
        type: SaleType::Cash,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-04-01'),
        method: PaymentMethod::BankTransfer,
    );

    expect($sale->status)->toBe(SaleStatus::Settled)
        ->and($sale->outstanding_cents)->toBe(0)
        ->and($sale->stand->fresh()->status)->toBe(StandStatus::Sold);

    /** Receivable raised then cleared by the same-day receipt, so it nets to nothing. */
    expect(netByAccount())->toBe([
        Account::ACCOUNTS_RECEIVABLE => 0,
        Account::STAND_SALES_REVENUE => -1_000_000,
        Account::COST_OF_SALES => 600_000,
        Account::LAND_INVENTORY => -600_000,
        Account::BANK => 1_000_000,
    ]);
});

it('carries the unpaid balance as a receivable on a payment plan', function () {
    $sale = $this->sell->handle(
        stand: ($this->stand)(),
        buyer: $this->buyer,
        type: SaleType::PaymentPlan,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-04-01'),
        method: PaymentMethod::Cash,
        depositCents: 2_000_00,
        instalmentCount: 8,
    );

    expect($sale->status)->toBe(SaleStatus::Outstanding)
        ->and($sale->paid_cents)->toBe(200_000)
        ->and($sale->outstanding_cents)->toBe(800_000)
        ->and($sale->stand->fresh()->status)->toBe(StandStatus::InProgress);

    $net = netByAccount();

    expect($net[Account::STAND_SALES_REVENUE])->toBe(-1_000_000)
        ->and($net[Account::ACCOUNTS_RECEIVABLE])->toBe(800_000)
        ->and($net[Account::BANK])->toBe(200_000);
});

it('builds an equal monthly schedule for the balance after the deposit', function () {
    $sale = $this->sell->handle(
        stand: ($this->stand)(),
        buyer: $this->buyer,
        type: SaleType::PaymentPlan,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-01-31'),
        method: PaymentMethod::Cash,
        depositCents: 2_000_00,
        instalmentCount: 4,
    );

    expect($sale->instalments->pluck('amount_cents')->all())->toBe([200_000, 200_000, 200_000, 200_000])
        ->and($sale->instalments->sum('amount_cents'))->toBe(800_000)
        /** Month-end dates must not roll into the next month. */
        ->and($sale->instalments->pluck('due_date')->map->toDateString()->all())
        ->toBe(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31']);
});

it('puts the rounding remainder in the final instalment', function () {
    $sale = $this->sell->handle(
        stand: ($this->stand)(['price_cents' => 1_000_01]),
        buyer: $this->buyer,
        type: SaleType::PaymentPlan,
        priceCents: 1_000_01,
        saleDate: Carbon::parse('2026-04-01'),
        method: PaymentMethod::Cash,
        depositCents: 0,
        instalmentCount: 3,
    );

    expect($sale->instalments->pluck('amount_cents')->all())->toBe([33_333, 33_333, 33_335])
        ->and($sale->instalments->sum('amount_cents'))->toBe(100_001);
});

it('does not credit the deposit against the instalment schedule', function () {
    $sale = $this->sell->handle(
        stand: ($this->stand)(),
        buyer: $this->buyer,
        type: SaleType::PaymentPlan,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-04-01'),
        method: PaymentMethod::Cash,
        depositCents: 2_000_00,
        instalmentCount: 8,
    );

    expect($sale->instalments->sum('paid_cents'))->toBe(0);
});

it('refuses to sell a stand that is already sold', function () {
    $stand = ($this->stand)(['status' => StandStatus::Sold, 'stand_number' => '2001']);

    $sell = fn () => $this->sell->handle(
        stand: $stand,
        buyer: $this->buyer,
        type: SaleType::Cash,
        priceCents: 10_000_00,
        saleDate: Carbon::today(),
        method: PaymentMethod::Cash,
    );

    expect($sell)->toThrow(AccountingException::class, 'Stand 2001 is not available for sale.');
});

it('refuses a buyer registered at another branch', function () {
    $sell = fn () => $this->sell->handle(
        stand: ($this->stand)(),
        buyer: Buyer::factory()->for(Branch::factory()->create(['code' => 'BYO']))->create(),
        type: SaleType::Cash,
        priceCents: 10_000_00,
        saleDate: Carbon::today(),
        method: PaymentMethod::Cash,
    );

    expect($sell)->toThrow(AccountingException::class, 'The buyer is registered at a different branch to the stand.');
});

it('refuses a deposit that covers the whole price', function () {
    $sell = fn () => $this->sell->handle(
        stand: ($this->stand)(),
        buyer: $this->buyer,
        type: SaleType::PaymentPlan,
        priceCents: 10_000_00,
        saleDate: Carbon::today(),
        method: PaymentMethod::Cash,
        depositCents: 10_000_00,
        instalmentCount: 6,
    );

    expect($sell)->toThrow(AccountingException::class, 'The deposit must be less than the selling price.');
});

it('leaves the stand available when the sale is refused', function () {
    $stand = ($this->stand)();

    try {
        $this->sell->handle(
            stand: $stand,
            buyer: $this->buyer,
            type: SaleType::PaymentPlan,
            priceCents: 10_000_00,
            saleDate: Carbon::today(),
            method: PaymentMethod::Cash,
            depositCents: 0,
            instalmentCount: 0,
        );
    } catch (AccountingException) {
        // Expected; the point of the test is that nothing was left half done.
    }

    expect($stand->fresh()->status)->toBe(StandStatus::Available)
        ->and(JournalEntry::count())->toBe(0);
});
