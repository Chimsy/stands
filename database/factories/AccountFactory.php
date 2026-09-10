<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(6000, 9999),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'position' => fake()->numberBetween(1, 99),
        ];
    }
}
