<?php

use App\Enums\StandStatus;
use App\Models\SitePlan;
use App\Models\Stand;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->plan = SitePlan::factory()->create();
});

describe('index', function () {
    it('returns 401 without a token', function () {
        Stand::factory()->for($this->plan)->create();

        $this->getJson(route('api.v1.stands.index'))->assertUnauthorized();
    });

    it('returns every stand on the plan', function () {
        Sanctum::actingAs(User::factory()->create());
        Stand::factory()->for($this->plan)->count(3)->create();

        $this->getJson(route('api.v1.stands.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('exposes stand geometry and pricing in the shape the site map expects', function () {
        Sanctum::actingAs(User::factory()->create());

        Stand::factory()->for($this->plan)->at(131.1, 52.8)->create([
            'stand_number' => '2001',
            'status' => StandStatus::Available,
            'block' => 'Block A',
            'road' => 'Chiremba Drive',
            'area_sqm' => 332,
            'price_cents' => 880000,
            'points' => [['x' => 126.4, 'y' => 39.3], ['x' => 138.7, 'y' => 40.4]],
        ]);

        $this->getJson(route('api.v1.stands.index'))
            ->assertOk()
            ->assertJsonPath('data.0.standNumber', '2001')
            ->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('data.0.block', 'Block A')
            ->assertJsonPath('data.0.road', 'Chiremba Drive')
            ->assertJsonPath('data.0.areaSqm', 332)
            ->assertJsonPath('data.0.price', 8800)
            ->assertJsonPath('data.0.centroid', ['x' => 131.1, 'y' => 52.8])
            ->assertJsonPath('data.0.points.0', ['x' => 126.4, 'y' => 39.3]);
    });

    it('filters by status', function () {
        Sanctum::actingAs(User::factory()->create());
        Stand::factory()->for($this->plan)->status(StandStatus::Sold)->create(['stand_number' => '1']);
        Stand::factory()->for($this->plan)->status(StandStatus::Available)->create(['stand_number' => '2']);

        $this->getJson(route('api.v1.stands.index', ['status' => 'sold']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.standNumber', '1');
    });

    it('filters by block and road', function () {
        Sanctum::actingAs(User::factory()->create());
        Stand::factory()->for($this->plan)->create(['stand_number' => '1', 'block' => 'Block A', 'road' => 'Chiremba Drive']);
        Stand::factory()->for($this->plan)->create(['stand_number' => '2', 'block' => 'Block B', 'road' => 'Chiremba Drive']);
        Stand::factory()->for($this->plan)->create(['stand_number' => '3', 'block' => 'Block A', 'road' => 'Mutare Road']);

        $this->getJson(route('api.v1.stands.index', ['block' => 'Block A', 'road' => 'Chiremba Drive']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.standNumber', '1');
    });

    it('searches stand number, road and block', function (string $term) {
        Sanctum::actingAs(User::factory()->create());
        Stand::factory()->for($this->plan)->create(['stand_number' => '2001', 'block' => 'Block A', 'road' => 'Chiremba Drive']);
        Stand::factory()->for($this->plan)->create(['stand_number' => '9999', 'block' => 'Block Z', 'road' => 'Mutare Road']);

        $this->getJson(route('api.v1.stands.index', ['search' => $term]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.standNumber', '2001');
    })->with(['200', 'Chiremba', 'Block A']);

    it('treats wildcard characters in a search as literal text', function () {
        Sanctum::actingAs(User::factory()->create());
        Stand::factory()->for($this->plan)->create(['stand_number' => '2001', 'road' => 'Chiremba Drive', 'block' => 'Block A']);

        $this->getJson(route('api.v1.stands.index', ['search' => '%']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('returns only stands within the given radius of a point', function () {
        Sanctum::actingAs(User::factory()->create());
        Stand::factory()->for($this->plan)->at(500.0, 500.0)->create(['stand_number' => 'centre']);
        Stand::factory()->for($this->plan)->at(560.0, 500.0)->create(['stand_number' => 'inside']);
        Stand::factory()->for($this->plan)->at(600.0, 600.0)->create(['stand_number' => 'corner-of-box']);
        Stand::factory()->for($this->plan)->at(900.0, 500.0)->create(['stand_number' => 'far']);

        $response = $this->getJson(route('api.v1.stands.index', ['x' => 500, 'y' => 500, 'radius' => 110]))
            ->assertOk();

        expect($response->json('data.*.standNumber'))
            ->toEqualCanonicalizing(['centre', 'inside']);
    });

    it('returns 422 for an unknown status', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.stands.index', ['status' => 'reserved']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('returns 422 when a proximity search is missing a coordinate', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.stands.index', ['x' => 500, 'radius' => 110]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('y');
    });
});

describe('show', function () {
    it('returns 401 without a token', function () {
        $stand = Stand::factory()->for($this->plan)->create(['stand_number' => '2001']);

        $this->getJson(route('api.v1.stands.show', $stand))->assertUnauthorized();
    });

    it('resolves a stand by its stand number', function () {
        Sanctum::actingAs(User::factory()->create());
        Stand::factory()->for($this->plan)->create(['stand_number' => '2001']);

        $this->getJson(route('api.v1.stands.show', '2001'))
            ->assertOk()
            ->assertJsonPath('data.standNumber', '2001');
    });

    it('returns 404 for an unknown stand number', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.stands.show', '404404'))->assertNotFound();
    });
});
