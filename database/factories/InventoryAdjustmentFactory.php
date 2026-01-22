<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryAdjustment>
 */
class InventoryAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stockBefore = $this->faker->numberBetween(0, 200);
        $type = $this->faker->randomElement(['add', 'remove', 'set']);
        $quantity = $this->faker->numberBetween(1, 50);

        $stockAfter = match ($type) {
            'add' => $stockBefore + $quantity,
            'remove' => max(0, $stockBefore - $quantity),
            'set' => $quantity,
        };

        $user = User::factory();

        return [
            'user_id' => $user,
            'product_id' => Product::factory()->for($user),
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}
