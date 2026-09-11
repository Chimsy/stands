<?php

use App\Actions\SellStand;
use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use App\Enums\StandStatus;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\SitePlan;
use App\Models\Stand;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * The sales and receipts ledgers take the same `branch` scope as the
 * statements, so an administrator reading the group dashboard can page through
 * the activity behind it without visiting each office in turn.
 */
beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    Carbon::setTestNow('2026-06-15');

    $this->harare = Branch::factory()->create(['code' => 'HRE']);
    $this->bulawayo = Branch::factory()->create(['code' => 'BYO']);

    $sell = fn (Branch $branch, string $standNumber) => app(SellStand::class)->handle(
        stand: Stand::factory()
            ->for(SitePlan::factory()->for($branch)->create())
            ->create([
                'stand_number' => $standNumber,
                'status' => StandStatus::Available,
                'price_cents' => 10_000_00,
                'cost_cents' => 6_000_00,
            ]),
        buyer: Buyer::factory()->for($branch)->create(),
        type: SaleType::Cash,
        priceCents: 10_000_00,
        saleDate: Carbon::parse('2026-05-01'),
        method: PaymentMethod::Cash,
    );

    $sell($this->harare, '2008');
    $sell($this->bulawayo, '5002');
});

afterEach(fn () => Carbon::setTestNow());

it('gives an administrator every branch when they ask for the group', function (string $route) {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route($route))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(route($route, ['branch' => 'group']))->assertOk()->assertJsonCount(2, 'data');
})->with(['api.v1.sales.index', 'api.v1.receipts.index']);

it('lets an administrator name a single branch without switching to it', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.sales.index', ['branch' => 'BYO']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.standNumber', '5002');
});

it('refuses the group ledger to an agent', function (string $route) {
    Sanctum::actingAs(agentAt($this->harare));

    $this->getJson(route($route, ['branch' => 'group']))
        ->assertForbidden()
        ->assertJsonPath('message', 'Consolidated views are for administrators only.');
})->with(['api.v1.sales.index', 'api.v1.receipts.index']);

it('refuses another branch\'s ledger to an agent', function () {
    Sanctum::actingAs(agentAt($this->harare));

    $this->getJson(route('api.v1.sales.index', ['branch' => 'BYO']))
        ->assertForbidden()
        ->assertJsonPath('message', 'You can only read your own branch.');
});

/**
 * `Sale::forBranch(null)` and `Payment::forBranch(null)` mean "every branch",
 * so an account with no branch at all must be stopped rather than allowed to
 * fall through to the widest possible answer.
 */
it('refuses the ledger to an account with no branch instead of showing everything', function (string $route) {
    Sanctum::actingAs(User::factory()->create(['branch_id' => null]));

    $this->getJson(route($route))
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account has not been assigned to a branch.');
})->with(['api.v1.sales.index', 'api.v1.receipts.index']);
