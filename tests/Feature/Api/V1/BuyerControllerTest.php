<?php

use App\Models\Branch;
use App\Models\Buyer;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['code' => 'HRE']);
    $this->agent = agentAt($this->branch);
});

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.buyers.index'))->assertUnauthorized();
});

it('registers a buyer against the caller\'s branch', function () {
    Sanctum::actingAs($this->agent);

    $this->postJson(route('api.v1.buyers.store'), [
        'name' => 'Tendai Moyo',
        'email' => 'tendai@example.com',
        'phone' => '+263 77 123 4567',
        'nationalId' => '63-123456A22',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Tendai Moyo')
        ->assertJsonPath('data.nationalId', '63-123456A22');

    expect(Buyer::sole()->branch_id)->toBe($this->branch->id);
});

it('returns 422 without a name', function () {
    Sanctum::actingAs($this->agent);

    $this->postJson(route('api.v1.buyers.store'), ['email' => 'tendai@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('lists only buyers registered at the caller\'s branch', function () {
    Sanctum::actingAs($this->agent);

    Buyer::factory()->for($this->branch)->create(['name' => 'Tendai Moyo']);
    Buyer::factory()->for(Branch::factory()->create(['code' => 'BYO']))->create(['name' => 'Sipho Ncube']);

    $this->getJson(route('api.v1.buyers.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Tendai Moyo');
});

it('finds a buyer by name, phone or identity number', function (string $term) {
    Sanctum::actingAs($this->agent);

    Buyer::factory()->for($this->branch)->create([
        'name' => 'Tendai Moyo',
        'phone' => '+263 77 123 4567',
        'national_id' => '63-123456A22',
    ]);
    Buyer::factory()->for($this->branch)->create(['name' => 'Rudo Chiweshe', 'phone' => '+263 71 000 0000', 'national_id' => '11-000000B00']);

    $this->getJson(route('api.v1.buyers.index', ['search' => $term]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Tendai Moyo');
})->with(['Tendai', '123 4567', '63-123456A22']);
