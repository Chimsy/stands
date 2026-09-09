<?php

namespace Database\Factories;

use App\Models\SitePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePlan>
 */
class SitePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $width = fake()->numberBetween(600, 1200);
        $height = fake()->numberBetween(600, 1200);

        return [
            'name' => fake()->streetName().' Estate',
            'subtitle' => 'Proposed medium density residential township',
            'authority' => fake()->city().' City Council',
            'note' => 'Sample layout for demonstration - not a survey document',
            'north_rotation' => fake()->randomFloat(2, -15, 15),
            'bounds' => ['width' => $width, 'height' => $height],
            'boundary' => [
                ['x' => 0, 'y' => 0],
                ['x' => $width, 'y' => 0],
                ['x' => $width, 'y' => $height],
                ['x' => 0, 'y' => $height],
            ],
            'roads' => [],
            'zones' => [],
            'blocks' => [],
        ];
    }
}
