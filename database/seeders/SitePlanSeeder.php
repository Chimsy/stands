<?php

namespace Database\Seeders;

use App\Models\SitePlan;
use App\Models\Stand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports the township fixture that used to ship with the front-end as static
 * JSON. The files in `database/data` remain the source of truth for the demo
 * dataset; this seeder is what turns them into queryable rows.
 */
class SitePlanSeeder extends Seeder
{
    private const INSERT_CHUNK = 500;

    public function run(): void
    {
        $plan = $this->readJson('site-plan.json');
        $stands = $this->readJson('stands.json');

        DB::transaction(function () use ($plan, $stands): void {
            $sitePlan = SitePlan::updateOrCreate(
                ['name' => $plan['name']],
                [
                    'subtitle' => $plan['subtitle'],
                    'authority' => $plan['authority'],
                    'note' => $plan['note'],
                    'north_rotation' => $plan['northRotation'],
                    'bounds' => $plan['bounds'],
                    'boundary' => $plan['boundary'],
                    'roads' => $plan['roads'],
                    'zones' => $plan['zones'],
                    'blocks' => $plan['blocks'],
                ],
            );

            $sitePlan->stands()->delete();

            $now = now();

            collect($stands)
                ->map(fn (array $stand): array => [
                    'site_plan_id' => $sitePlan->id,
                    'stand_number' => $stand['standNumber'],
                    'status' => $stand['status'],
                    'block' => $stand['block'],
                    'road' => $stand['road'],
                    'area_sqm' => $stand['areaSqm'],
                    'price_cents' => (int) round($stand['price'] * 100),
                    'centroid_x' => $stand['centroid']['x'],
                    'centroid_y' => $stand['centroid']['y'],
                    'points' => json_encode($stand['points'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    /** The fixture's `updatedAt` is when the listing last changed. */
                    'updated_at' => $stand['updatedAt'],
                ])
                ->chunk(self::INSERT_CHUNK)
                ->each(fn ($chunk) => Stand::insert($chunk->all()));
        });
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readJson(string $file): array
    {
        $path = database_path('data/'.$file);

        if (! is_readable($path)) {
            throw new RuntimeException("Missing site plan fixture [{$path}].");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
