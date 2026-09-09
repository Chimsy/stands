<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const DEMO_EMAIL = 'test@example.com';

    /**
     * Seeds the demo account and the sample township.
     *
     * Kept idempotent so `composer setup` and a plain re-seed are safe to run
     * against a database that already has data.
     */
    public function run(): void
    {
        if (! User::query()->where('email', self::DEMO_EMAIL)->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => self::DEMO_EMAIL,
            ]);
        }

        $this->call(SitePlanSeeder::class);
    }
}
