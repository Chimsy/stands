<?php

use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\Payment;
use App\Models\SitePlan;
use App\Models\Stand;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    Carbon::setTestNow('2026-06-15');

    $this->branch = Branch::factory()->create(['code' => 'HRE']);
    $this->plan = SitePlan::factory()->for($this->branch)->create(['name' => 'Riverstone Park Estate']);
    $this->agent = agentAt($this->branch);

    $this->sale = app(SellStand::class)->handle(
        stand: Stand::factory()->for($this->plan)->create([
            'stand_number' => '2001',
            'block' => 'Block A',
            'road' => 'Chiremba Drive',
            'area_sqm' => 332,
            'status' => StandStatus::Available,
            'price_cents' => 10_000_00,
            'cost_cents' => 6_000_00,
        ]),
        buyer: Buyer::factory()->for($this->branch)->create(['name' => 'Tendai Moyo']),
        type: SaleType::PaymentPlan,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-03-01'),
        method: PaymentMethod::Cash,
        soldBy: $this->agent,
        depositCents: 2_000_00,
        instalmentCount: 8,
    );
});

afterEach(fn () => Carbon::setTestNow());

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.receipts.index'))->assertUnauthorized();
});

it('issues a receipt when a payment is recorded', function () {
    Sanctum::actingAs($this->agent);

    $this->postJson(route('api.v1.sales.payments.store', $this->sale->reference), [
        'amount' => 1000,
        'method' => 'mobile-money',
        'paidOn' => '2026-04-01',
        'externalReference' => 'ECO-99812',
    ])
        ->assertCreated()
        ->assertJsonPath('data.receiptNumber', 'RCP-HRE-000002')
        ->assertJsonPath('data.amount', 1000)
        ->assertJsonPath('data.method', 'mobile-money')
        ->assertJsonPath('data.externalReference', 'ECO-99812')
        ->assertJsonPath('data.buyer.name', 'Tendai Moyo')
        ->assertJsonPath('data.stand.standNumber', '2001')
        ->assertJsonPath('data.stand.township', 'Riverstone Park Estate')
        ->assertJsonPath('data.branch.code', 'HRE')
        ->assertJsonPath('data.sale.outstanding', 7000);
});

it('returns 422 when the receipt would exceed what is owed', function () {
    Sanctum::actingAs($this->agent);

    $this->postJson(route('api.v1.sales.payments.store', $this->sale->reference), [
        'amount' => 8000.01,
        'method' => 'cash',
        'paidOn' => '2026-04-01',
    ])->assertUnprocessable();

    expect(Payment::count())->toBe(1);
});

it('shows a receipt with everything needed to print it', function () {
    Sanctum::actingAs($this->agent);

    $receipt = Payment::sole();

    $this->getJson(route('api.v1.receipts.show', $receipt->receipt_number))
        ->assertOk()
        ->assertJsonPath('data.receiptNumber', 'RCP-HRE-000001')
        ->assertJsonPath('data.amount', 2000)
        ->assertJsonPath('data.paidOn', '2026-03-01')
        ->assertJsonPath('data.receivedBy', $this->agent->name)
        ->assertJsonPath('data.sale.reference', $this->sale->reference)
        ->assertJsonStructure(['data' => [
            'receiptNumber', 'paidOn', 'issuedAt', 'amount', 'method', 'receivedBy',
            'branch' => ['code', 'name', 'city'],
            'buyer' => ['name'],
            'sale' => ['reference', 'saleDate', 'type', 'price', 'paid', 'outstanding'],
            'stand' => ['standNumber', 'block', 'road', 'areaSqm', 'township'],
        ]]);
});

it('reports the balance as it stands now when a receipt is reprinted', function () {
    Sanctum::actingAs($this->agent);

    $deposit = Payment::sole();

    $this->postJson(route('api.v1.sales.payments.store', $this->sale->reference), [
        'amount' => 3000,
        'method' => 'cash',
        'paidOn' => '2026-04-01',
    ])->assertCreated();

    /** The receipt still shows what was received then, but the balance has moved on. */
    $this->getJson(route('api.v1.receipts.show', $deposit->receipt_number))
        ->assertOk()
        ->assertJsonPath('data.amount', 2000)
        ->assertJsonPath('data.sale.outstanding', 5000);
});

it('returns 404 for a receipt raised by another branch', function () {
    Sanctum::actingAs($this->agent);

    $elsewhere = Branch::factory()->create(['code' => 'BYO']);
    $theirs = app(SellStand::class)->handle(
        stand: Stand::factory()
            ->for(SitePlan::factory()->for($elsewhere)->create())
            ->create(['status' => StandStatus::Available, 'price_cents' => 1000, 'cost_cents' => 500]),
        buyer: Buyer::factory()->for($elsewhere)->create(),
        type: SaleType::Cash,
        priceCents: 1000,
        saleDate: Carbon::parse('2026-03-01'),
        method: PaymentMethod::Cash,
    );

    $this->getJson(route('api.v1.receipts.show', $theirs->payments()->sole()->receipt_number))
        ->assertNotFound();
});

it('lists only receipts issued at the caller\'s branch', function () {
    Sanctum::actingAs($this->agent);

    $elsewhere = Branch::factory()->create(['code' => 'BYO']);
    app(SellStand::class)->handle(
        stand: Stand::factory()
            ->for(SitePlan::factory()->for($elsewhere)->create())
            ->create(['status' => StandStatus::Available, 'price_cents' => 1000, 'cost_cents' => 500]),
        buyer: Buyer::factory()->for($elsewhere)->create(),
        type: SaleType::Cash,
        priceCents: 1000,
        saleDate: Carbon::parse('2026-03-01'),
        method: PaymentMethod::Cash,
    );

    $this->getJson(route('api.v1.receipts.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.receiptNumber', 'RCP-HRE-000001');
});
