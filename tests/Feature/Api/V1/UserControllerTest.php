<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.user.show'))->assertUnauthorized();
});

it('returns the account behind the current token', function () {
    $user = User::factory()->create(['name' => 'Sales Agent', 'email' => 'agent@example.com']);
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => 'Sales Agent',
            'email' => 'agent@example.com',
        ]]);
});
