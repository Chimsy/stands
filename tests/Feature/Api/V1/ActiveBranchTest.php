<?php

use App\Models\Branch;
use App\Models\Buyer;
use App\Models\SitePlan;
use App\Models\Stand;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->harare = Branch::factory()->create(['code' => 'HRE', 'name' => 'Harare Branch']);
    $this->bulawayo = Branch::factory()->create(['code' => 'BYO', 'name' => 'Bulawayo Branch']);

    $this->hararePlan = SitePlan::factory()->create(['branch_id' => $this->harare->id, 'name' => 'Riverstone Park']);
    $this->bulawayoPlan = SitePlan::factory()->create(['branch_id' => $this->bulawayo->id, 'name' => 'Hillside Park']);
});

it('leaves an agent in their own branch when no header is sent', function () {
    Sanctum::actingAs(agentAt($this->harare));

    $this->getJson(route('api.v1.site-plan.show'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Riverstone Park');
});

it('refuses to let an agent work from another branch', function () {
    Sanctum::actingAs(agentAt($this->harare));

    $this->withHeader('X-Branch', 'BYO')
        ->getJson(route('api.v1.site-plan.show'))
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not entitled to work from that branch.');
});

it('lets an agent name their own branch explicitly', function () {
    Sanctum::actingAs(agentAt($this->harare));

    $this->withHeader('X-Branch', 'hre')
        ->getJson(route('api.v1.site-plan.show'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Riverstone Park');
});

it('moves an administrator to whichever branch they ask for', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->withHeader('X-Branch', 'BYO')
        ->getJson(route('api.v1.site-plan.show'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Hillside Park');

    $this->withHeader('X-Branch', 'HRE')
        ->getJson(route('api.v1.site-plan.show'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Riverstone Park');
});

it('falls back to an administrator\'s home branch when no header is sent', function () {
    Sanctum::actingAs(adminAt($this->bulawayo));

    $this->getJson(route('api.v1.site-plan.show'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Hillside Park');
});

it('refuses a branch code that does not exist', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->withHeader('X-Branch', 'ZZZ')
        ->getJson(route('api.v1.site-plan.show'))
        ->assertForbidden()
        ->assertJsonPath('message', 'There is no branch with that code.');
});

it('scopes the stand list to the branch the administrator is working from', function () {
    Stand::factory()->count(3)->create(['site_plan_id' => $this->hararePlan->id]);
    Stand::factory()->count(5)->create(['site_plan_id' => $this->bulawayoPlan->id]);

    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.stands.index'))->assertOk()->assertJsonCount(3, 'data');

    $this->withHeader('X-Branch', 'BYO')
        ->getJson(route('api.v1.stands.index'))
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

/**
 * Route binding is what stops one branch reaching another's records, so an
 * administrator's selection has to reach it too - otherwise switching offices
 * would change the lists but not the documents behind them.
 */
it('resolves a stand only within the branch the administrator is working from', function () {
    Stand::factory()->create(['site_plan_id' => $this->bulawayoPlan->id, 'stand_number' => '5001']);

    Sanctum::actingAs(adminAt($this->harare));

    $this->getJson(route('api.v1.stands.show', '5001'))->assertNotFound();

    $this->withHeader('X-Branch', 'BYO')
        ->getJson(route('api.v1.stands.show', '5001'))
        ->assertOk()
        ->assertJsonPath('data.standNumber', '5001');
});

it('registers a buyer against the branch the administrator is working from', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->withHeader('X-Branch', 'BYO')
        ->postJson(route('api.v1.buyers.store'), ['name' => 'Tendai Moyo'])
        ->assertCreated();

    expect(Buyer::query()->where('name', 'Tendai Moyo')->value('branch_id'))->toBe($this->bulawayo->id);
});

it('shows an agent one branch to choose from and an administrator all of them', function () {
    Sanctum::actingAs(agentAt($this->harare));
    $this->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.role', 'sales')
        ->assertJsonPath('data.isAdmin', false)
        ->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.branches.0.code', 'HRE');

    Sanctum::actingAs(adminAt($this->harare));
    $this->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonPath('data.isAdmin', true)
        ->assertJsonCount(2, 'data.branches');
});

it('reports the branch the request was worked from, not the home one', function () {
    Sanctum::actingAs(adminAt($this->harare));

    $this->withHeader('X-Branch', 'BYO')
        ->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.branch.code', 'BYO');
});

it('ignores the header for a user who has no branch at all', function () {
    Sanctum::actingAs(User::factory()->create(['branch_id' => null]));

    $this->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.branch', null)
        ->assertJsonCount(0, 'data.branches');
});
