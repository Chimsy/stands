<?php

use App\Enums\StandStatus;
use App\Models\SitePlan;
use App\Models\Stand;
use Database\Seeders\BranchSeeder;
use Database\Seeders\SitePlanSeeder;

/**
 * The fixtures in `database/data` are the source of the demo townships, so this
 * covers the mapping from that JSON onto the schema rather than the seeder's
 * mechanics.
 */
beforeEach(function () {
    $this->seed(BranchSeeder::class);
});

it('imports every township fixture against its branch', function () {
    $this->seed(SitePlanSeeder::class);

    expect(SitePlan::pluck('name', 'slug')->all())->toBe([
        'riverstone-park' => 'Riverstone Park Estate',
        'hillside-park' => 'Hillside Park Estate',
    ]);

    $riverstone = SitePlan::where('slug', 'riverstone-park')->sole();

    expect($riverstone->branch->code)->toBe('HRE')
        ->and($riverstone->bounds)->toBe(['width' => 1131.9, 'height' => 886.2])
        ->and($riverstone->roads)->not->toBeEmpty()
        ->and($riverstone->stands()->count())->toBe(1223)
        ->and(SitePlan::where('slug', 'hillside-park')->sole()->branch->code)->toBe('BYO');
});

it('keeps stand numbers unique across townships', function () {
    $this->seed(SitePlanSeeder::class);

    expect(Stand::count())->toBe(2446)
        ->and(Stand::distinct()->count('stand_number'))->toBe(2446);
});

it('converts fixture prices into minor units and costs the stand for inventory', function () {
    $this->seed(SitePlanSeeder::class);

    $stand = Stand::where('stand_number', '2001')->sole();

    expect($stand->price_cents)->toBe(880000)
        ->and($stand->price)->toBe(8800.0)
        ->and($stand->cost_cents)->toBe(528000)
        ->and($stand->status)->toBe(StandStatus::Available)
        ->and($stand->area_sqm)->toBe(332)
        ->and($stand->centroid_x)->toBe(131.1)
        ->and($stand->updated_at->toDateString())->toBe('2026-04-28');
});

it('replaces existing stands instead of duplicating them when run twice', function () {
    $this->seed(SitePlanSeeder::class);
    $this->seed(SitePlanSeeder::class);

    expect(SitePlan::count())->toBe(2)
        ->and(Stand::count())->toBe(2446);
});
