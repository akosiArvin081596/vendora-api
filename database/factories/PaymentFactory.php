<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $paidAt = $this->faker->dateTimeBetween('-7 days', 'now');

        return [
            'user_id' => $user,
            'order_id' => Order::factory()->for($user),
            'payment_number' => strtoupper($this->faker->bothify('PAY-###')),
            'paid_at' => $paidAt,
            'amount' => $this->faker->numberBetween(200, 5000),
            'currency' => 'PHP',
            'method' => $this->faker->randomElement(['cash', 'card', 'online', 'credit']),
            'status' => $this->faker->randomElement(['completed', 'pending', 'refunded']),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn () => [
            'order_id' => null,
            'customer_id' => Customer::factory(),
            'method' => 'credit',
            'status' => 'pending',
        ]);
    }
}
