<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\FoodMenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FoodMenuReservation>
 */
class FoodMenuReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'food_menu_item_id' => FoodMenuItem::factory(),
            'user_id' => User::factory(),
            'customer_id' => null,
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->optional(0.7)->phoneNumber(),
            'servings' => $this->faker->numberBetween(1, 5),
            'status' => ReservationStatus::Pending,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'reserved_at' => $this->faker->optional(0.5)->dateTimeBetween('now', '+3 days'),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Cancelled,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Completed,
        ]);
    }
}
