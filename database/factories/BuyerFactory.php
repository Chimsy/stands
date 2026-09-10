<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Buyer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Buyer>
 */
class BuyerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+263 7# ### ####'),
            'national_id' => fake()->numerify('##-######'.fake()->randomLetter().'##'),
        ];
    }
}
