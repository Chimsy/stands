<?php

use App\Actions\PostJournalEntry;
use App\Actions\RecordPayment;
use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\SitePlan;
use App\Models\Stand;
use App\Reports\BalanceSheet;
use App\Reports\IncomeStatement;
use App\Reports\ReceivablesAgeing;
use App\Reports\TrialBalance;
use App\Support\LedgerLine;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;

/**
 * A hand-worked set of books, so every figure below can be checked by reading
 * the arrangement rather than by running the code.
 *
 * Harare  opening inventory 20,000
 *         one cash sale     12,000 price, 7,000 cost, settled 2026-03-01
 * Bulawayo opening inventory 9,000
 *         one plan sale      9,000 price, 5,000 cost, 1,800 deposit,
 *                            3 instalments of 2,400 from 2026-04-01
 */
beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    Carbon::setTestNow('2026-06-15');

    $this->harare = Branch::factory()->create(['code' => 'HRE']);
    $this->bulawayo = Branch::factory()->create(['code' => 'BYO']);

    $open = function (Branch $branch, int $cents): void {
        app(PostJournalEntry::class)->handle(
            branch: $branch,
            date: Carbon::parse('2026-01-01'),
            description: 'Opening inventory',
            lines: [
                LedgerLine::debit(Account::LAND_INVENTORY, $cents),
                LedgerLine::credit(Account::SHARE_CAPITAL, $cents),
            ],
        );
    };

    $open($this->harare, 20_000_00);
    $open($this->bulawayo, 9_000_00);

    $standAt = fn (Branch $branch, int $price, int $cost) => Stand::factory()
        ->for(SitePlan::factory()->for($branch)->create())
        ->create(['status' => StandStatus::Available, 'price_cents' => $price, 'cost_cents' => $cost]);

    app(SellStand::class)->handle(
        stand: $standAt($this->harare, 12_000_00, 7_000_00),
        buyer: Buyer::factory()->for($this->harare)->create(),
        type: SaleType::Cash,
        priceCents: 12_000_00,
        saleDate: Carbon::parse('2026-03-01'),
        method: PaymentMethod::BankTransfer,
    );

    $this->plan = app(SellStand::class)->handle(
        stand: $standAt($this->bulawayo, 9_000_00, 5_000_00),
        buyer: Buyer::factory()->for($this->bulawayo)->create(),
        type: SaleType::PaymentPlan,
        priceCents: 9_000_00,
        saleDate: Carbon::parse('2026-03-01'),
        method: PaymentMethod::Cash,
        depositCents: 1_800_00,
        instalmentCount: 3,
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reports a trial balance that agrees for one branch', function () {
    $report = app(TrialBalance::class)->handle($this->harare, Carbon::parse('2026-06-15'));

    expect($report['inBalance'])->toBeTrue()
        ->and($report['branch'])->toBe('HRE')
        ->and(collect($report['rows'])->pluck('debitCents', 'code')->all())->toBe([
            Account::BANK => 1_200_000,
            Account::ACCOUNTS_RECEIVABLE => 0,
            Account::LAND_INVENTORY => 1_300_000,
            Account::SHARE_CAPITAL => 0,
            Account::STAND_SALES_REVENUE => 0,
            Account::COST_OF_SALES => 700_000,
        ]);
});

it('balances the sheet for each branch and for the group', function (?string $code, int $assets, int $equity) {
    $branch = $code === null ? null : Branch::where('code', $code)->sole();

    $report = app(BalanceSheet::class)->handle($branch, Carbon::parse('2026-06-15'));

    expect($report['inBalance'])->toBeTrue()
        ->and($report['assetsCents'])->toBe($assets)
        ->and($report['equityCents'])->toBe($equity)
        ->and($report['liabilitiesCents'])->toBe(0);
})->with([
    /** Harare: bank 12,000 + inventory 13,000 = capital 20,000 + retained 5,000. */
    'harare' => ['HRE', 2_500_000, 2_500_000],
    /** Bulawayo: bank 1,800 + receivable 7,200 + inventory 4,000 = capital 9,000 + retained 4,000. */
    'bulawayo' => ['BYO', 1_300_000, 1_300_000],
    'group' => [null, 3_800_000, 3_800_000],
]);

it('recognises the full margin in the period of sale, not of collection', function () {
    $report = app(IncomeStatement::class)->handle(
        $this->bulawayo,
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
    );

    /** Only 1,800 of the 9,000 was collected in March, but the whole margin is earned. */
    expect($report['revenueCents'])->toBe(900_000)
        ->and($report['expensesCents'])->toBe(500_000)
        ->and($report['netIncomeCents'])->toBe(400_000);
});

it('excludes activity outside the reporting period', function () {
    $report = app(IncomeStatement::class)->handle(
        null,
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30'),
    );

    expect($report['revenueCents'])->toBe(0)
        ->and($report['netIncomeCents'])->toBe(0);
});

it('adds up to the group when the branches are combined', function () {
    $at = Carbon::parse('2026-06-15');

    $harare = app(BalanceSheet::class)->handle($this->harare, $at);
    $bulawayo = app(BalanceSheet::class)->handle($this->bulawayo, $at);
    $group = app(BalanceSheet::class)->handle(null, $at);

    expect($harare['assetsCents'] + $bulawayo['assetsCents'])->toBe($group['assetsCents'])
        ->and($harare['retainedEarningsCents'] + $bulawayo['retainedEarningsCents'])
        ->toBe($group['retainedEarningsCents']);
});

it('ages the receivable by how late each instalment is', function () {
    /** Due 1 Apr, 1 May and 1 Jun; nothing has been paid since the deposit. */
    $report = app(ReceivablesAgeing::class)->handle($this->bulawayo, Carbon::parse('2026-06-15'));

    expect($report['totalCents'])->toBe(720_000)
        ->and($report['buckets'])->toBe([
            'notYetDue' => 0,
            'days1To30' => 240_000,
            'days31To60' => 240_000,
            'days61To90' => 240_000,
            'over90Days' => 0,
        ]);
});

it('ties the ageing total to the receivable on the balance sheet', function () {
    $at = Carbon::parse('2026-06-15');

    $receivable = collect(app(BalanceSheet::class)->handle($this->bulawayo, $at)['assets'])
        ->firstWhere('code', Account::ACCOUNTS_RECEIVABLE)['amountCents'];

    expect(app(ReceivablesAgeing::class)->handle($this->bulawayo, $at)['totalCents'])->toBe($receivable);
});

it('moves an instalment out of arrears once it is receipted', function () {
    app(RecordPayment::class)->handle($this->plan, 2_400_00, PaymentMethod::Cash, Carbon::parse('2026-04-02'));

    $report = app(ReceivablesAgeing::class)->handle($this->bulawayo, Carbon::parse('2026-06-15'));

    expect($report['totalCents'])->toBe(480_000)
        ->and($report['buckets']['days61To90'])->toBe(0);
});
