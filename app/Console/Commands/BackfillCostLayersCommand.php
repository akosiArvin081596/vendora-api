<?php

namespace App\Console\Commands;

use App\Models\InventoryCostLayer;
use App\Models\Product;
use Illuminate\Console\Command;

class BackfillCostLayersCommand extends Command
{
    protected $signature = 'inventory:backfill-cost-layers';

    protected $description = 'Create migration cost layers for products with stock but no active layers';

    public function handle(): int
    {
        $products = Product::query()
            ->where('stock', '>', 0)
            ->whereDoesntHave('costLayers', function ($query) {
                $query->where('remaining_quantity', '>', 0);
            })
            ->get();

        if ($products->isEmpty()) {
            $this->info('No products need backfilling.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($products as $product) {
            InventoryCostLayer::query()->create([
                'product_id' => $product->id,
                'user_id' => $product->user_id,
                'quantity' => $product->stock,
                'remaining_quantity' => $product->stock,
                'unit_cost' => $product->cost ?? $product->price,
                'acquired_at' => $product->created_at ?? now(),
                'reference' => 'MIGRATION',
            ]);
            $count++;
        }

        $this->info("Backfilled cost layers for {$count} product(s).");

        return self::SUCCESS;
    }
}
