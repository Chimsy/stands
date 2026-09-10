<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * One demo account per branch, so signing in as either shows that branch's
     * stock and books.
     *
     * @var array<string, string>
     */
    private const DEMO_USERS = [
        'test@example.com' => 'HRE',
        'bulawayo@example.com' => 'BYO',
    ];

    /**
     * Seeds the reference data, the townships, the demo accounts and a trading
     * history for each branch.
     *
     * Kept idempotent so `composer setup` and a plain re-seed are safe to run
     * against a database that already has data.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            ChartOfAccountsSeeder::class,
            SitePlanSeeder::class,
        ]);

        foreach (self::DEMO_USERS as $email => $branchCode) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            User::factory()->create([
                'name' => Branch::query()->where('code', $branchCode)->value('name').' Agent',
                'email' => $email,
                'branch_id' => Branch::query()->where('code', $branchCode)->value('id'),
            ]);
        }

        $this->call(TradingHistorySeeder::class);
    }
}
