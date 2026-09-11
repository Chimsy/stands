<?php

use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SitePlan;
use App\Models\Stand;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-06-15');

    $this->harare = Branch::factory()->create(['code' => 'HRE', 'name' => 'Harare Branch']);
    $this->bulawayo = Branch::factory()->create(['code' => 'BYO', 'name' => 'Bulawayo Branch']);

    $hararePlan = SitePlan::factory()->create(['branch_id' => $this->harare->id]);
    $bulawayoPlan = SitePlan::factory()->create(['branch_id' => $this->bulawayo->id]);

    Stand::factory()->count(4)->status(StandStatus::Available)->create(['site_plan_id' => $hararePlan->id]);
    $sold = Stand::factory()->count(2)->status(StandStatus::Sold)->create(['site_plan_id' => $hararePlan->id]);
    Stand::factory()->count(1)->status(StandStatus::InProgress)->create(['site_plan_id' => $hararePlan->id]);
    $bulawayoStand = Stand::factory()->status(StandStatus::Sold)->create(['site_plan_id' => $bulawayoPlan->id]);
    Stand::factory()->count(2)->status(StandStatus::Available)->create(['site_plan_id' => $bulawayoPlan->id]);

    /**
     * Stands and buyers are attached explicitly: left to the factories they
     * would each conjure a branch of their own, and the group totals would then
     * consolidate offices that do not exist.
     */
    $this->hararesale = Sale::factory()->create([
        'branch_id' => $this->harare->id,
        'stand_id' => $sold->first()->id,
        'buyer_id' => Buyer::factory()->create(['branch_id' => $this->harare->id])->id,
        'sale_date' => '2026-03-10',
        'type' => SaleType::Cash,
        'price_cents' => 10_000_00,
        'cost_cents' => 6_000_00,
    ]);

    Payment::factory()->create([
        'branch_id' => $this->harare->id,
        'sale_id' => $this->hararesale->id,
        'paid_on' => '2026-04-02',
        'amount_cents' => 5_000_00,
    ]);

    /** Bulawayo: one 4,000 plan sale in May, nothing received. */
    Sale::factory()->create([
        'branch_id' => $this->bulawayo->id,
        'stand_id' => $bulawayoStand->id,
        'buyer_id' => Buyer::factory()->create(['branch_id' => $this->bulawayo->id])->id,
        'sale_date' => '2026-05-20',
        'type' => SaleType::PaymentPlan,
        'price_cents' => 4_000_00,
        'cost_cents' => 2_400_00,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.dashboard.show'))->assertUnauthorized();
});

it('is closed to sales agents', function () {
    Sanctum::actingAs(agentAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show'))
        ->assertForbidden()
        ->assertJsonPath('message', 'This is available to administrators only.');
});

it('reports the branch the administrator is working from by default', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show'))
        ->assertOk()
        ->assertJsonPath('data.branch', 'HRE')
        ->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.totals.standsTotal', 7)
        ->assertJsonPath('data.totals.standsAvailable', 4)
        ->assertJsonPath('data.totals.standsSold', 2)
        ->assertJsonPath('data.totals.standsInProgress', 1)
        ->assertJsonPath('data.totals.salesCount', 1)
        ->assertJsonPath('data.totals.valueCents', 10_000_00)
        ->assertJsonPath('data.totals.grossProfitCents', 4_000_00)
        ->assertJsonPath('data.totals.collectedCents', 5_000_00)
        ->assertJsonPath('data.totals.outstandingCents', 5_000_00);
});

it('follows the administrator to another branch', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->withHeader('X-Branch', 'BYO')
        ->getJson(route('api.v1.dashboard.show'))
        ->assertOk()
        ->assertJsonPath('data.branch', 'BYO')
        ->assertJsonPath('data.totals.standsTotal', 3)
        ->assertJsonPath('data.totals.valueCents', 4_000_00)
        ->assertJsonPath('data.totals.collectedCents', 0)
        ->assertJsonPath('data.totals.outstandingCents', 4_000_00);
});

it('consolidates every branch when asked for the group', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show', ['branch' => 'group']))
        ->assertOk()
        ->assertJsonPath('data.branch', null)
        ->assertJsonCount(2, 'data.branches')
        ->assertJsonPath('data.totals.standsTotal', 10)
        ->assertJsonPath('data.totals.salesCount', 2)
        ->assertJsonPath('data.totals.valueCents', 14_000_00)
        ->assertJsonPath('data.totals.outstandingCents', 9_000_00)
        ->assertJsonPath('data.branches.0.code', 'BYO')
        ->assertJsonPath('data.branches.0.outstandingCents', 4_000_00)
        ->assertJsonPath('data.branches.1.code', 'HRE')
        ->assertJsonPath('data.branches.1.collectedCents', 5_000_00);
});

it('emits every month in the period, including the empty ones', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $response = $this->getJson(route('api.v1.dashboard.show', ['branch' => 'group']))->assertOk();

    /** January to June inclusive: the year to date, not only the months that traded. */
    expect(array_column($response->json('data.monthly'), 'month'))
        ->toBe(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06']);

    $months = collect($response->json('data.monthly'))->keyBy('month');

    expect($months['2026-03']['valueCents'])->toBe(10_000_00)
        ->and($months['2026-03']['collectedCents'])->toBe(0)
        ->and($months['2026-04']['collectedCents'])->toBe(5_000_00)
        ->and($months['2026-05']['valueCents'])->toBe(4_000_00)
        ->and($months['2026-02']['salesCount'])->toBe(0);
});

it('splits the period by sale type', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show', ['branch' => 'group']))
        ->assertOk()
        ->assertJsonPath('data.mix.0.type', 'cash')
        ->assertJsonPath('data.mix.0.salesCount', 1)
        ->assertJsonPath('data.mix.0.valueCents', 10_000_00)
        ->assertJsonPath('data.mix.1.type', 'payment-plan')
        ->assertJsonPath('data.mix.1.valueCents', 4_000_00);
});

it('narrows the period to the requested dates', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show', ['branch' => 'group', 'from' => '2026-04-01', 'to' => '2026-05-31']))
        ->assertOk()
        ->assertJsonPath('data.from', '2026-04-01')
        ->assertJsonPath('data.to', '2026-05-31')
        /** Only the May signing falls inside, but the collection in April does too. */
        ->assertJsonPath('data.totals.salesCount', 1)
        ->assertJsonPath('data.totals.valueCents', 4_000_00)
        ->assertJsonPath('data.totals.collectedCents', 5_000_00);
});

/**
 * The receivable is what the whole book owes as at the date, not what the
 * period contributed, so it ties back to Accounts Receivable on the balance
 * sheet however the period is narrowed.
 */
it('keeps the outstanding figure cumulative even when the period is narrow', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show', ['branch' => 'group', 'from' => '2026-06-01']))
        ->assertOk()
        ->assertJsonPath('data.totals.salesCount', 0)
        ->assertJsonPath('data.totals.outstandingCents', 9_000_00);
});

it('lists the latest signings with what is still owed on each', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show'))
        ->assertOk()
        ->assertJsonCount(1, 'data.recentSales')
        ->assertJsonPath('data.recentSales.0.reference', $this->hararesale->reference)
        ->assertJsonPath('data.recentSales.0.branch', 'HRE')
        ->assertJsonPath('data.recentSales.0.paidCents', 5_000_00)
        ->assertJsonPath('data.recentSales.0.outstandingCents', 5_000_00);
});

it('ranks agents by what they signed', function () {
    $agent = agentAt($this->harare);
    $this->hararesale->update(['sold_by' => $agent->id]);

    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.dashboard.show', ['branch' => 'group']))
        ->assertOk()
        ->assertJsonCount(1, 'data.topAgents')
        ->assertJsonPath('data.topAgents.0.name', $agent->name)
        ->assertJsonPath('data.topAgents.0.branch', 'HRE')
        ->assertJsonPath('data.topAgents.0.salesCount', 1)
        ->assertJsonPath('data.topAgents.0.valueCents', 10_000_00);
});
