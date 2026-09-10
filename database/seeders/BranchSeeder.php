<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * The sales offices. Each one runs its own townships and raises its own
 * document numbers, but they all post into the one ledger.
 */
class BranchSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, city: string}>
     */
    private const BRANCHES = [
        ['code' => 'HRE', 'name' => 'Harare Branch', 'city' => 'Harare'],
        ['code' => 'BYO', 'name' => 'Bulawayo Branch', 'city' => 'Bulawayo'],
    ];

    public function run(): void
    {
        foreach (self::BRANCHES as $branch) {
            Branch::updateOrCreate(['code' => $branch['code']], $branch);
        }
    }
}
