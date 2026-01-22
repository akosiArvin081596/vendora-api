<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional(0.7)->sentence(),
            'sku' => strtoupper($this->faker->unique()->bothify('??-####')),
            'barcode' => $this->faker->optional(0.5)->ean13(),
            'price' => $this->faker->numberBetween(1000, 500000),
            'cost' => $this->faker->optional(0.8)->numberBetween(500, 400000),
            'currency' => 'PHP',
            'unit' => $this->faker->randomElement(['pc', 'kg', 'pack', 'bottle', 'bag', 'box', 'set']),
            'stock' => $this->faker->numberBetween(0, 200),
            'min_stock' => $this->faker->numberBetween(5, 20),
            'max_stock' => $this->faker->numberBetween(50, 200),
            'image' => null,
            'is_active' => $this->faker->boolean(90),
            'is_ecommerce' => true,
        ];
    }

    public function withBarcode(): static
    {
        return $this->state(fn (array $attributes) => [
            'barcode' => $this->faker->unique()->ean13(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function notEcommerce(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ecommerce' => false,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(0, 5),
            'min_stock' => 10,
        ]);
    }
}
