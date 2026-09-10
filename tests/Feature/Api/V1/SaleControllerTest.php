<?php

use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\SitePlan;
use App\Models\Stand;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    Carbon::setTestNow('2026-06-15');

    $this->branch = Branch::factory()->create(['code' => 'HRE']);
    $this->plan = SitePlan::factory()->for($this->branch)->create();
    $this->agent = agentAt($this->branch);
    $this->buyer = Buyer::factory()->for($this->branch)->create(['name' => 'Tendai Moyo']);

    $this->stand = fn (array $attributes = []) => Stand::factory()->for($this->plan)->create([
        'status' => StandStatus::Available,
        'price_cents' => 10_000_00,
        'cost_cents' => 6_000_00,
        ...$attributes,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('returns 401 without a token', function () {
    $this->postJson(route('api.v1.sales.store'))->assertUnauthorized();
});

it('books a cash sale and settles it the same day', function () {
    Sanctum::actingAs($this->agent);
    ($this->stand)(['stand_number' => '2001']);

    $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '2001',
        'buyerId' => $this->buyer->id,
        'type' => 'cash',
        'price' => 9500,
        'saleDate' => '2026-06-01',
        'method' => 'bank-transfer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'cash')
        ->assertJsonPath('data.status', 'settled')
        ->assertJsonPath('data.price', 9500)
        ->assertJsonPath('data.paid', 9500)
        ->assertJsonPath('data.outstanding', 0)
        ->assertJsonPath('data.standNumber', '2001')
        ->assertJsonPath('data.buyer.name', 'Tendai Moyo');

    expect(Stand::where('stand_number', '2001')->sole()->status)->toBe(StandStatus::Sold);
});

it('books a payment plan with its schedule', function () {
    Sanctum::actingAs($this->agent);
    ($this->stand)(['stand_number' => '2002']);

    $response = $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '2002',
        'buyerId' => $this->buyer->id,
        'type' => 'payment-plan',
        'price' => 12000,
        'deposit' => 2400,
        'instalmentCount' => 12,
        'saleDate' => '2026-06-01',
        'method' => 'cash',
    ])->assertCreated();

    $response->assertJsonPath('data.status', 'outstanding')
        ->assertJsonPath('data.paid', 2400)
        ->assertJsonPath('data.outstanding', 9600)
        ->assertJsonCount(12, 'data.instalments')
        ->assertJsonPath('data.instalments.0.dueDate', '2026-07-01')
        ->assertJsonPath('data.instalments.0.amount', 800);

    expect(Stand::where('stand_number', '2002')->sole()->status)->toBe(StandStatus::InProgress);
});

it('returns 404 for a stand belonging to another branch', function () {
    Sanctum::actingAs($this->agent);

    $elsewhere = Branch::factory()->create(['code' => 'BYO']);
    Stand::factory()
        ->for(SitePlan::factory()->for($elsewhere)->create())
        ->create(['stand_number' => '5001', 'status' => StandStatus::Available]);

    $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '5001',
        'buyerId' => $this->buyer->id,
        'type' => 'cash',
        'price' => 9500,
        'saleDate' => '2026-06-01',
        'method' => 'cash',
    ])->assertNotFound();
});

it('rejects a buyer registered at another branch', function () {
    Sanctum::actingAs($this->agent);
    ($this->stand)(['stand_number' => '2003']);

    $elsewhere = Buyer::factory()->for(Branch::factory()->create(['code' => 'BYO']))->create();

    $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '2003',
        'buyerId' => $elsewhere->id,
        'type' => 'cash',
        'price' => 9500,
        'saleDate' => '2026-06-01',
        'method' => 'cash',
    ])->assertUnprocessable()->assertJsonValidationErrors('buyerId');
});

it('returns 422 when selling a stand that is already sold', function () {
    Sanctum::actingAs($this->agent);
    ($this->stand)(['stand_number' => '2004', 'status' => StandStatus::Sold]);

    $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '2004',
        'buyerId' => $this->buyer->id,
        'type' => 'cash',
        'price' => 9500,
        'saleDate' => '2026-06-01',
        'method' => 'cash',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Stand 2004 is not available for sale.');
});

it('rejects a sale dated in the future', function () {
    Sanctum::actingAs($this->agent);
    ($this->stand)(['stand_number' => '2005']);

    $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '2005',
        'buyerId' => $this->buyer->id,
        'type' => 'cash',
        'price' => 9500,
        'saleDate' => '2026-06-16',
        'method' => 'cash',
    ])->assertUnprocessable()->assertJsonValidationErrors('saleDate');
});

it('rejects a deposit on a cash sale', function () {
    Sanctum::actingAs($this->agent);
    ($this->stand)(['stand_number' => '2006']);

    $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '2006',
        'buyerId' => $this->buyer->id,
        'type' => 'cash',
        'price' => 9500,
        'deposit' => 1000,
        'saleDate' => '2026-06-01',
        'method' => 'cash',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['deposit' => 'A cash sale is settled in full, so it takes no deposit.']);
});

it('rejects a plan with no instalments', function () {
    Sanctum::actingAs($this->agent);
    ($this->stand)(['stand_number' => '2007']);

    $this->postJson(route('api.v1.sales.store'), [
        'standNumber' => '2007',
        'buyerId' => $this->buyer->id,
        'type' => 'payment-plan',
        'price' => 9500,
        'deposit' => 1000,
        'saleDate' => '2026-06-01',
        'method' => 'cash',
    ])->assertUnprocessable()->assertJsonValidationErrors('instalmentCount');
});

it('lists only the sales booked at the caller\'s branch', function () {
    Sanctum::actingAs($this->agent);

    $sell = app(SellStand::class);

    $sell->handle(
        stand: ($this->stand)(['stand_number' => '2008']),
        buyer: $this->buyer,
        type: SaleType::Cash,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-05-01'),
        method: PaymentMethod::Cash,
    );

    $elsewhere = Branch::factory()->create(['code' => 'BYO']);
    $sell->handle(
        stand: Stand::factory()
            ->for(SitePlan::factory()->for($elsewhere)->create())
            ->create(['stand_number' => '5002', 'status' => StandStatus::Available, 'price_cents' => 1000, 'cost_cents' => 500]),
        buyer: Buyer::factory()->for($elsewhere)->create(),
        type: SaleType::Cash,
        priceCents: 1000,
        saleDate: Carbon::parse('2026-05-01'),
        method: PaymentMethod::Cash,
    );

    $this->getJson(route('api.v1.sales.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.standNumber', '2008');
});

it('returns 404 for a sale reference from another branch', function () {
    Sanctum::actingAs($this->agent);

    $elsewhere = Branch::factory()->create(['code' => 'BYO']);
    $sale = app(SellStand::class)->handle(
        stand: Stand::factory()
            ->for(SitePlan::factory()->for($elsewhere)->create())
            ->create(['status' => StandStatus::Available, 'price_cents' => 1000, 'cost_cents' => 500]),
        buyer: Buyer::factory()->for($elsewhere)->create(),
        type: SaleType::Cash,
        priceCents: 1000,
        saleDate: Carbon::parse('2026-05-01'),
        method: PaymentMethod::Cash,
    );

    $this->getJson(route('api.v1.sales.show', $sale->reference))->assertNotFound();
});

it('finds the sale behind a given stand', function () {
    Sanctum::actingAs($this->agent);

    $sell = app(SellStand::class);

    foreach (['2010', '2011'] as $number) {
        $sell->handle(
            stand: ($this->stand)(['stand_number' => $number]),
            buyer: $this->buyer,
            type: SaleType::Cash,
            priceCents: 10_000_00,
            saleDate: Carbon::parse('2026-05-01'),
            method: PaymentMethod::Cash,
        );
    }

    $this->getJson(route('api.v1.sales.index', ['standNumber' => '2011']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.standNumber', '2011');
});
