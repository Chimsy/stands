<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\SitePlan;
use App\Models\Stand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports the township fixtures that used to ship with the front-end as static
 * JSON. The files in `database/data` remain the source of truth for the demo
 * dataset; this seeder is what turns them into queryable rows.
 *
 * Stands are imported as they stand in the fixture. TradingHistorySeeder then
 * replaces the fixture's sold and in-progress markers with real sales, so a
 * stand's status is always something the ledger can account for.
 */
class SitePlanSeeder extends Seeder
{
    private const INSERT_CHUNK = 500;

    /**
     * What the business carries a stand at in land inventory, as a share of its
     * asking price. A single ratio keeps the demo margin obvious; a real system
     * would cost each stand from its own acquisition and servicing spend.
     */
    private const COST_RATIO = 0.6;

    /**
     * Fixture directory under `database/data`, mapped to the branch that sells it.
     *
     * @var array<string, string>
     */
    private const TOWNSHIPS = [
        'riverstone-park' => 'HRE',
        'hillside-park' => 'BYO',
    ];

    public function run(): void
    {
        foreach (self::TOWNSHIPS as $slug => $branchCode) {
            $branch = Branch::query()->where('code', $branchCode)->sole();

            $this->importTownship($slug, $branch);
        }
    }

    private function importTownship(string $slug, Branch $branch): void
    {
        $plan = $this->readJson($slug, 'site-plan.json');
        $stands = $this->readJson($slug, 'stands.json');

        DB::transaction(function () use ($slug, $branch, $plan, $stands): void {
            $sitePlan = SitePlan::updateOrCreate(
                ['slug' => $slug],
                [
                    'branch_id' => $branch->id,
                    'name' => $plan['name'],
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
                ->map(function (array $stand) use ($sitePlan, $now): array {
                    $priceCents = (int) round($stand['price'] * 100);

                    return [
                        'site_plan_id' => $sitePlan->id,
                        'stand_number' => $stand['standNumber'],
                        'status' => $stand['status'],
                        'block' => $stand['block'],
                        'road' => $stand['road'],
                        'area_sqm' => $stand['areaSqm'],
                        'price_cents' => $priceCents,
                        'cost_cents' => (int) round($priceCents * self::COST_RATIO),
                        'centroid_x' => $stand['centroid']['x'],
                        'centroid_y' => $stand['centroid']['y'],
                        'points' => json_encode($stand['points'], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        /** The fixture's `updatedAt` is when the listing last changed. */
                        'updated_at' => $stand['updatedAt'],
                    ];
                })
                ->chunk(self::INSERT_CHUNK)
                ->each(fn ($chunk) => Stand::insert($chunk->all()));
        });
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readJson(string $slug, string $file): array
    {
        $path = database_path("data/{$slug}/{$file}");

        if (! is_readable($path)) {
            throw new RuntimeException("Missing township fixture [{$path}].");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
