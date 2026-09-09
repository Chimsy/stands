<?php

namespace App\Models;

use Database\Factories\SitePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The surveyed layout a township's stands are drawn on.
 *
 * @property-read array{width: float, height: float} $bounds
 * @property-read array<int, array{x: float, y: float}> $boundary
 */
#[Fillable([
    'name',
    'subtitle',
    'authority',
    'note',
    'north_rotation',
    'bounds',
    'boundary',
    'roads',
    'zones',
    'blocks',
])]
class SitePlan extends Model
{
    /** @use HasFactory<SitePlanFactory> */
    use HasFactory;

    /**
     * @return HasMany<Stand, $this>
     */
    public function stands(): HasMany
    {
        return $this->hasMany(Stand::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'north_rotation' => 'float',
            'bounds' => 'array',
            'boundary' => 'array',
            'roads' => 'array',
            'zones' => 'array',
            'blocks' => 'array',
        ];
    }
}
