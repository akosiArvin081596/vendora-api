<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();

        return [
            'user_id' => User::factory(),
            'name' => $firstName.' '.$lastName,
            'first_name' => $firstName,
            'middle_name' => $this->faker->optional()->firstName(),
            'last_name' => $lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'status' => $this->faker->randomElement(['active', 'vip', 'inactive']),
            'orders_count' => $this->faker->numberBetween(0, 30),
            'total_spent' => $this->faker->numberBetween(0, 30000),
            'credit_balance' => 0,
        ];
    }

    public function withCredit(int $amount = 10000): static
    {
        return $this->state(fn () => [
            'credit_balance' => $amount,
        ]);
    }
}
