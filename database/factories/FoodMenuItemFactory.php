<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FoodMenuItem>
 */
class FoodMenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalServings = $this->faker->numberBetween(10, 100);

        return [
            'user_id' => User::factory(),
            'store_id' => null,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional(0.7)->sentence(),
            'category' => $this->faker->randomElement(['Main Course', 'Dessert', 'Appetizer', 'Beverage', 'Snack']),
            'price' => $this->faker->numberBetween(5000, 100000),
            'currency' => 'PHP',
            'image' => null,
            'total_servings' => $totalServings,
            'reserved_servings' => 0,
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_servings' => 10,
            'reserved_servings' => 10,
        ]);
    }
}
