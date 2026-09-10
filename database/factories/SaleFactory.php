<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\Sale;
use App\Models\Stand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a sale row directly. Tests that care about the ledger should go
 * through App\Actions\SellStand instead, so the journal entries exist too.
 *
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(50, 400) * 100_00;

        return [
            'branch_id' => Branch::factory(),
            'stand_id' => Stand::factory(),
            'buyer_id' => Buyer::factory(),
            'reference' => 'SALE-TST-'.fake()->unique()->numerify('######'),
            'sale_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'type' => SaleType::Cash,
            'status' => SaleStatus::Outstanding,
            'price_cents' => $price,
            'cost_cents' => (int) round($price * 0.6),
            'deposit_cents' => 0,
            'instalment_count' => 0,
        ];
    }
}
