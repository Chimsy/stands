<?php

use App\Models\Branch;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.user.show'))->assertUnauthorized();
});

it('returns the account behind the current token, with the branch it belongs to', function () {
    $branch = Branch::factory()->create(['code' => 'HRE', 'name' => 'Harare Branch', 'city' => 'Harare']);
    $user = User::factory()->create(['name' => 'Sales Agent', 'email' => 'agent@example.com', 'branch_id' => $branch->id]);
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => 'Sales Agent',
            'email' => 'agent@example.com',
            'branch' => ['code' => 'HRE', 'name' => 'Harare Branch', 'city' => 'Harare'],
        ]]);
});

it('reports a null branch for a user who has not been assigned one', function () {
    Sanctum::actingAs(User::factory()->create(['branch_id' => null]));

    $this->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.branch', null);
});
