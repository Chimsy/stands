<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Shared by every demo account; this is a sample data set, not a live one. */
    private const DEMO_PASSWORD = '#p@$$123!';

    /**
     * The demo accounts: an agent at each branch, and one administrator who can
     * work from either.
     *
     * `was` carries the address the account used to be seeded under, so a
     * database seeded before the addresses changed is renamed in place rather
     * than growing a second copy of the same person - the sales they booked
     * hang off the user row.
     *
     * @var list<array{email: string, was: ?string, name: string, branch: string, role: UserRole}>
     */
    private const DEMO_USERS = [
        [
            'email' => 'harare@chimsy.co.za',
            'was' => 'test@example.com',
            'name' => 'Harare Branch Agent',
            'branch' => 'HRE',
            'role' => UserRole::Sales,
        ],
        [
            'email' => 'bulawayo@chimsy.co.za',
            'was' => 'bulawayo@example.com',
            'name' => 'Bulawayo Branch Agent',
            'branch' => 'BYO',
            'role' => UserRole::Sales,
        ],
        [
            /** Head office. Assigned to Harare so an unqualified request lands somewhere, but free to work from any branch. */
            'email' => 'magaya@chimsy.co.za',
            'was' => null,
            'name' => 'Magaya',
            'branch' => 'HRE',
            'role' => UserRole::Admin,
        ],
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

        $this->seedDemoUsers();

        $this->call(TradingHistorySeeder::class);
    }

    private function seedDemoUsers(): void
    {
        foreach (self::DEMO_USERS as $demo) {
            $addresses = array_filter([$demo['email'], $demo['was']]);

            $user = User::query()->whereIn('email', $addresses)->first() ?? new User;

            $user->forceFill([
                'email' => $demo['email'],
                'name' => $demo['name'],
                'role' => $demo['role'],
                'branch_id' => Branch::query()->where('code', $demo['branch'])->value('id'),
                'email_verified_at' => now(),
                'password' => Hash::make(self::DEMO_PASSWORD),
            ])->save();
        }
    }
}
