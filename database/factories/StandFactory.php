<?php

namespace Database\Factories;

use App\Enums\StandStatus;
use App\Models\SitePlan;
use App\Models\Stand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stand>
 */
class StandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $x = fake()->randomFloat(1, 20, 1100);
        $y = fake()->randomFloat(1, 20, 860);
        $halfWidth = fake()->randomFloat(1, 5, 9);
        $halfHeight = fake()->randomFloat(1, 10, 16);

        return [
            'site_plan_id' => SitePlan::factory(),
            'stand_number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'status' => fake()->randomElement(StandStatus::cases()),
            'block' => 'Block '.fake()->randomLetter(),
            'road' => fake()->streetName(),
            'area_sqm' => fake()->numberBetween(200, 900),
            'price_cents' => fake()->numberBetween(50, 400) * 100_00,
            'centroid_x' => $x,
            'centroid_y' => $y,
            'points' => [
                ['x' => $x - $halfWidth, 'y' => $y - $halfHeight],
                ['x' => $x + $halfWidth, 'y' => $y - $halfHeight],
                ['x' => $x + $halfWidth, 'y' => $y + $halfHeight],
                ['x' => $x - $halfWidth, 'y' => $y + $halfHeight],
            ],
        ];
    }

    public function status(StandStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    /**
     * Places the stand at an exact point so proximity tests can be deterministic.
     */
    public function at(float $x, float $y): static
    {
        return $this->state(fn (): array => ['centroid_x' => $x, 'centroid_y' => $y]);
    }
}
