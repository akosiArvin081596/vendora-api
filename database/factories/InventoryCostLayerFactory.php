<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryCostLayer>
 */
class InventoryCostLayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(5, 100);

        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'inventory_adjustment_id' => null,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => $this->faker->numberBetween(500, 50000),
            'acquired_at' => now(),
            'reference' => null,
        ];
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining_quantity' => 0,
        ]);
    }

    public function partial(int $remaining): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining_quantity' => $remaining,
        ]);
    }
}
