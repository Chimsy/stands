<?php

use App\Enums\StandStatus;
use App\Models\SitePlan;
use App\Models\Stand;
use Database\Seeders\SitePlanSeeder;

/**
 * The fixtures in `database/data` are the source of the demo township, so this
 * covers the mapping from that JSON onto the schema rather than the seeder's
 * mechanics.
 */
it('imports the township fixture', function () {
    $this->seed(SitePlanSeeder::class);

    $plan = SitePlan::sole();

    expect($plan->name)->toBe('Riverstone Park Estate')
        ->and($plan->bounds)->toBe(['width' => 1131.9, 'height' => 886.2])
        ->and($plan->roads)->not->toBeEmpty()
        ->and($plan->stands()->count())->toBe(1223);
});

it('converts fixture prices into minor units and back', function () {
    $this->seed(SitePlanSeeder::class);

    $stand = Stand::where('stand_number', '2001')->sole();

    expect($stand->price_cents)->toBe(880000)
        ->and($stand->price)->toBe(8800.0)
        ->and($stand->status)->toBe(StandStatus::Available)
        ->and($stand->area_sqm)->toBe(332)
        ->and($stand->centroid_x)->toBe(131.1)
        ->and($stand->updated_at->toDateString())->toBe('2026-04-28');
});

it('replaces existing stands instead of duplicating them when run twice', function () {
    $this->seed(SitePlanSeeder::class);
    $this->seed(SitePlanSeeder::class);

    expect(SitePlan::count())->toBe(1)
        ->and(Stand::count())->toBe(1223);
});
