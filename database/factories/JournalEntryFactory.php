<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'reference' => 'JE-TST-'.fake()->unique()->numerify('######'),
            'entry_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'description' => fake()->sentence(4),
        ];
    }
}
