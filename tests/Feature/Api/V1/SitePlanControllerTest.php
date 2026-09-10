<?php

use App\Models\Branch;
use App\Models\SitePlan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->agent = agentAt($this->branch);
});

it('returns 401 without a token', function () {
    SitePlan::factory()->for($this->branch)->create();

    $this->getJson(route('api.v1.site-plan.show'))->assertUnauthorized();
});

it('returns the plan in the shape the site map expects', function () {
    Sanctum::actingAs($this->agent);

    SitePlan::factory()->for($this->branch)->create([
        'name' => 'Riverstone Park Estate',
        'subtitle' => 'Proposed medium density residential township',
        'authority' => 'City of Harare',
        'note' => 'Sample layout for demonstration - not a survey document',
        'north_rotation' => 5,
        'bounds' => ['width' => 1131.9, 'height' => 886.2],
        'boundary' => [['x' => 0, 'y' => 0]],
        'roads' => [['id' => 'road-arterial-n', 'name' => 'Chiremba Drive', 'kind' => 'arterial']],
        'zones' => [['id' => 'zone-1-1-0', 'kind' => 'open-space']],
        'blocks' => [['id' => 'block-0-0', 'name' => 'Block A', 'x' => 195.3, 'y' => 167.9]],
    ]);

    $this->getJson(route('api.v1.site-plan.show'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Riverstone Park Estate')
        ->assertJsonPath('data.authority', 'City of Harare')
        ->assertJsonPath('data.northRotation', 5)
        ->assertJsonPath('data.bounds', ['width' => 1131.9, 'height' => 886.2])
        ->assertJsonPath('data.roads.0.name', 'Chiremba Drive')
        ->assertJsonPath('data.zones.0.kind', 'open-space')
        ->assertJsonPath('data.blocks.0.name', 'Block A');
});

it('returns 404 when no plan has been seeded', function () {
    Sanctum::actingAs($this->agent);

    $this->getJson(route('api.v1.site-plan.show'))->assertNotFound();
});
