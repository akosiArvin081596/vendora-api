<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $orderDate = $this->faker->dateTimeBetween('-7 days', 'now');

        return [
            'user_id' => $user,
            'customer_id' => Customer::factory()->for($user),
            'order_number' => strtoupper($this->faker->bothify('ORD-###')),
            'ordered_at' => $orderDate->format('Y-m-d'),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'items_count' => $this->faker->numberBetween(1, 10),
            'total' => $this->faker->numberBetween(200, 5000),
            'currency' => 'PHP',
        ];
    }
}
