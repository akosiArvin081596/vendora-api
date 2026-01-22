<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();
        $customers = Customer::query()->where('user_id', $user->id)->get();
        $products = Product::query()->where('user_id', $user->id)->get();

        if ($customers->isEmpty()) {
            $customers = Customer::factory()->count(5)->for($user)->create();
        }

        if ($products->isEmpty()) {
            $products = Product::factory()->count(5)->for($user)->create();
        }

        foreach (range(1, 5) as $index) {
            $customer = $customers->random();
            $order = Order::query()->create([
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'ordered_at' => now()->subDays($index)->toDateString(),
                'status' => fake()->randomElement(['pending', 'processing', 'completed', 'cancelled']),
                'items_count' => 0,
                'total' => 0,
                'currency' => 'PHP',
            ]);

            $itemsCount = fake()->numberBetween(1, 5);
            $lineTotal = 0;

            foreach (range(1, $itemsCount) as $itemIndex) {
                $product = $products->random();
                $quantity = fake()->numberBetween(1, 3);
                $unitPrice = $product->price;
                $lineTotal += $quantity * $unitPrice;

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                ]);
            }

            $order->update([
                'items_count' => $itemsCount,
                'total' => $lineTotal,
            ]);

            $customer->update([
                'orders_count' => $customer->orders_count + 1,
                'total_spent' => $customer->total_spent + $lineTotal,
            ]);
        }
    }
}
