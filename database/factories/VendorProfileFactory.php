<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->vendor(),
            'business_name' => fake()->company(),
            'subscription_plan' => 'free',
        ];
    }

    /**
     * Indicate that the vendor has a basic subscription.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_plan' => 'basic',
        ]);
    }

    /**
     * Indicate that the vendor has a premium subscription.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_plan' => 'premium',
        ]);
    }
}
