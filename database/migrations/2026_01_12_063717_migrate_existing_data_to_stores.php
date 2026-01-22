<?php

use App\Models\Customer;
use App\Models\InventoryAdjustment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For each existing user with products or orders, create a default store
        User::query()
            ->where(function ($query) {
                $query->whereHas('products')
                    ->orWhereHas('orders')
                    ->orWhereHas('customers');
            })
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    DB::transaction(function () use ($user) {
                        // Create default store for this user
                        $store = Store::create([
                            'user_id' => $user->id,
                            'name' => $user->business_name ?? 'Main Store',
                            'code' => 'MAIN',
                            'is_active' => true,
                        ]);

                        // Migrate product stock to store_products
                        $user->products()->each(function (Product $product) use ($store) {
                            StoreProduct::create([
                                'store_id' => $store->id,
                                'product_id' => $product->id,
                                'stock' => $product->stock ?? 0,
                                'min_stock' => $product->min_stock,
                                'max_stock' => $product->max_stock,
                                'is_available' => true,
                            ]);
                        });

                        // Update existing orders to reference default store
                        Order::where('user_id', $user->id)
                            ->whereNull('store_id')
                            ->update(['store_id' => $store->id]);

                        // Update existing customers to reference default store
                        Customer::where('user_id', $user->id)
                            ->whereNull('store_id')
                            ->update(['store_id' => $store->id]);

                        // Update existing inventory adjustments
                        InventoryAdjustment::where('user_id', $user->id)
                            ->whereNull('store_id')
                            ->update(['store_id' => $store->id]);
                    });
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete all stores and store_products created by this migration
        // Note: This will cascade delete store_products due to foreign key
        Store::where('code', 'MAIN')->delete();

        // Set store_id to null on related tables
        Order::whereNotNull('store_id')->update(['store_id' => null]);
        Customer::whereNotNull('store_id')->update(['store_id' => null]);
        InventoryAdjustment::whereNotNull('store_id')->update(['store_id' => null]);
    }
};
