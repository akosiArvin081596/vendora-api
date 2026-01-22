<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::query()->get();
        $products = Product::query()->get();

        if ($orders->isEmpty() || $products->isEmpty()) {
            return;
        }

        foreach ($orders as $order) {
            $itemsCount = fake()->numberBetween(1, 4);
            $lineTotal = 0;

            foreach (range(1, $itemsCount) as $index) {
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
        }
    }
}
