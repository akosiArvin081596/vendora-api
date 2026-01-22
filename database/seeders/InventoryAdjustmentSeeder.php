<?php

namespace Database\Seeders;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryAdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();
        $products = Product::query()->where('user_id', $user->id)->get();

        if ($products->isEmpty()) {
            $products = Product::factory()->count(5)->for($user)->create();
        }

        foreach (range(1, 5) as $index) {
            $product = $products->random();
            $stockBefore = $product->stock;
            $type = fake()->randomElement(['add', 'remove', 'set']);
            $quantity = fake()->numberBetween(1, 10);

            $stockAfter = match ($type) {
                'add' => $stockBefore + $quantity,
                'remove' => max(0, $stockBefore - $quantity),
                'set' => $quantity,
            };

            $product->update(['stock' => $stockAfter]);

            InventoryAdjustment::query()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => fake()->optional()->sentence(),
            ]);
        }
    }
}
