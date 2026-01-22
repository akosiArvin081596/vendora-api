<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();
        $orders = Order::query()->where('user_id', $user->id)->get();

        if ($orders->isEmpty()) {
            $orders = Order::factory()->count(5)->for($user)->create();
        }

        foreach ($orders->values() as $index => $order) {
            Payment::query()->create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'payment_number' => 'PAY-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'paid_at' => now()->subDays($index)->setTime(10 + $index, 15),
                'amount' => max(1, $order->total),
                'currency' => 'PHP',
                'method' => fake()->randomElement(['cash', 'card', 'online']),
                'status' => fake()->randomElement(['completed', 'pending', 'refunded']),
            ]);
        }
    }
}
