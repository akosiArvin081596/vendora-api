<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['stock_in', 'stock_out', 'sale', 'expense', 'adjustment', 'return']);
        $category = in_array($type, ['sale', 'expense', 'return']) ? 'financial' : 'inventory';

        return [
            'user_id' => User::factory(),
            'store_id' => null,
            'product_id' => Product::factory(),
            'order_id' => null,
            'type' => $type,
            'category' => $category,
            'quantity' => $category === 'inventory' ? $this->faker->numberBetween(-50, 50) : null,
            'amount' => $category === 'financial' ? $this->faker->numberBetween(100, 50000) : null,
            'balance_qty' => null,
            'balance_amount' => null,
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'description' => $this->faker->sentence(),
        ];
    }

    public function stockIn(): static
    {
        return $this->state(fn () => [
            'type' => 'stock_in',
            'category' => 'inventory',
            'quantity' => $this->faker->numberBetween(1, 100),
            'amount' => null,
        ]);
    }

    public function stockOut(): static
    {
        return $this->state(fn () => [
            'type' => 'stock_out',
            'category' => 'inventory',
            'quantity' => $this->faker->numberBetween(-100, -1),
            'amount' => null,
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn () => [
            'type' => 'sale',
            'category' => 'financial',
            'quantity' => null,
            'amount' => $this->faker->numberBetween(100, 50000),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn () => [
            'type' => 'expense',
            'category' => 'financial',
            'quantity' => null,
            'amount' => $this->faker->numberBetween(-50000, -100),
        ]);
    }
}
