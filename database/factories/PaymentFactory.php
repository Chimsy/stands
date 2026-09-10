<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'sale_id' => Sale::factory(),
            'receipt_number' => 'RCP-TST-'.fake()->unique()->numerify('######'),
            'paid_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'amount_cents' => fake()->numberBetween(50, 500) * 100,
            'method' => fake()->randomElement(PaymentMethod::cases()),
        ];
    }
}
