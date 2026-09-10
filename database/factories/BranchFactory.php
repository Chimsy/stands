<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'code' => fake()->unique()->regexify('[A-Z]{3}'),
            'name' => $city.' Branch',
            'city' => $city,
        ];
    }
}
