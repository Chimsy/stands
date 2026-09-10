<?php

namespace App\Models;

use App\Enums\StandStatus;
use App\Models\Concerns\ScopedToUserBranch;
use Database\Factories\StandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single surveyed stand on a site plan.
 *
 * @property-read array<int, array{x: float, y: float}> $points
 */
#[Fillable([
    'site_plan_id',
    'stand_number',
    'status',
    'block',
    'road',
    'area_sqm',
    'price_cents',
    'cost_cents',
    'centroid_x',
    'centroid_y',
    'points',
])]
class Stand extends Model
{
    /** @use HasFactory<StandFactory> */
    use HasFactory, ScopedToUserBranch;

    /**
     * Stand numbers, not database keys, are what buyers and agents quote, so
     * they are the public identifier the API routes on.
     */
    public function getRouteKeyName(): string
    {
        return 'stand_number';
    }

    /**
     * @return BelongsTo<SitePlan, $this>
     */
    public function sitePlan(): BelongsTo
    {
        return $this->belongsTo(SitePlan::class);
    }

    /**
     * A stand is sold at most once, which the unique key on sales.stand_id
     * enforces.
     *
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * The asking (or sold) price in major currency units, derived from the
     * exact minor-unit value held in the database.
     *
     * @return Attribute<float, never>
     */
    protected function price(): Attribute
    {
        return Attribute::get(fn (): float => $this->price_cents / 100);
    }

    /**
     * Stands belonging to a branch's townships. Every stand query a signed-in
     * user makes goes through this, so one branch never sees another's stock.
     */
    #[Scope]
    protected function forBranch(Builder $query, ?Branch $branch): Builder
    {
        return $query->when(
            $branch,
            fn (Builder $query) => $query->whereHas('sitePlan', fn (Builder $plans) => $plans->whereBelongsTo($branch)),
            // A user with no branch is deliberately shown nothing at all.
            fn (Builder $query) => $query->whereRaw('1 = 0'),
        );
    }

    #[Scope]
    protected function available(Builder $query): Builder
    {
        return $query->where('status', StandStatus::Available);
    }

    #[Scope]
    protected function status(Builder $query, StandStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Matches the stand number, road or block, mirroring the search box on the
     * site map so the client and the API agree on what a "match" means.
     */
    /**
     * What the stand is carried at in land inventory, in major currency units.
     *
     * @return Attribute<float, never>
     */
    protected function cost(): Attribute
    {
        return Attribute::get(fn (): float => $this->cost_cents / 100);
    }

    #[Scope]
    protected function matching(Builder $query, string $term): Builder
    {
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($pattern): void {
            $query->where('stand_number', 'like', $pattern)
                ->orWhere('road', 'like', $pattern)
                ->orWhere('block', 'like', $pattern);
        });
    }

    /**
     * Stands whose centroid falls within `$radius` metres of a plan-space point.
     *
     * The bounding-box clauses are redundant with the distance test but let the
     * centroid index do the initial filtering instead of scanning every stand.
     *
     * The radius is squared in SQL rather than in PHP on purpose. PDO binds
     * floats as strings, and SQLite only applies numeric affinity to a
     * parameter compared against a column, so a pre-computed `?` on the right
     * of the comparison would stay text - and every number sorts before text,
     * which silently makes the distance test always true. Multiplying the two
     * parameters together forces them to be read as numbers.
     */
    #[Scope]
    protected function near(Builder $query, float $x, float $y, float $radius): Builder
    {
        return $query
            ->whereBetween('centroid_x', [$x - $radius, $x + $radius])
            ->whereBetween('centroid_y', [$y - $radius, $y + $radius])
            ->whereRaw(
                '((centroid_x - ?) * (centroid_x - ?)) + ((centroid_y - ?) * (centroid_y - ?)) <= ? * ?',
                [$x, $x, $y, $y, $radius, $radius],
            );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StandStatus::class,
            'area_sqm' => 'integer',
            'price_cents' => 'integer',
            'cost_cents' => 'integer',
            'centroid_x' => 'float',
            'centroid_y' => 'float',
            'points' => 'array',
        ];
    }
}
