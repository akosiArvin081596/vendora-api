<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $groceries = Category::query()->where('slug', 'groceries')->first();
        $hardware = Category::query()->where('slug', 'hardware')->first();
        $electronics = Category::query()->where('slug', 'electronics')->first();
        $beverages = Category::query()->where('slug', 'beverages')->first();
        $household = Category::query()->where('slug', 'household')->first();

        $products = [
            // Groceries
            [
                'category_id' => $groceries?->id,
                'name' => 'Premium Rice 5kg',
                'description' => 'High-quality locally sourced rice',
                'sku' => 'GR-1001',
                'barcode' => '4801234567890',
                'price' => 125000,
                'cost' => 100000,
                'unit' => 'bag',
                'stock' => 45,
                'min_stock' => 10,
                'max_stock' => 100,
            ],
            [
                'category_id' => $groceries?->id,
                'name' => 'Cooking Oil 1L',
                'description' => 'Pure vegetable cooking oil',
                'sku' => 'GR-1020',
                'barcode' => '4801234567891',
                'price' => 16000,
                'cost' => 12000,
                'unit' => 'bottle',
                'stock' => 78,
                'min_stock' => 20,
                'max_stock' => 150,
            ],
            [
                'category_id' => $groceries?->id,
                'name' => 'Sugar 1kg',
                'description' => 'Refined white sugar',
                'sku' => 'GR-1030',
                'barcode' => '4801234567892',
                'price' => 7500,
                'cost' => 5500,
                'unit' => 'pack',
                'stock' => 120,
                'min_stock' => 30,
                'max_stock' => 200,
            ],
            // Hardware
            [
                'category_id' => $hardware?->id,
                'name' => 'Cement 40kg',
                'description' => 'Portland cement for construction',
                'sku' => 'HW-2001',
                'barcode' => '4801234567893',
                'price' => 36000,
                'cost' => 28000,
                'unit' => 'bag',
                'stock' => 25,
                'min_stock' => 5,
                'max_stock' => 50,
            ],
            [
                'category_id' => $hardware?->id,
                'name' => 'PVC Pipe 1 inch',
                'description' => '4 meters PVC pipe for plumbing',
                'sku' => 'HW-1023',
                'barcode' => '4801234567894',
                'price' => 9500,
                'cost' => 7000,
                'unit' => 'pc',
                'stock' => 60,
                'min_stock' => 15,
                'max_stock' => 100,
            ],
            [
                'category_id' => $hardware?->id,
                'name' => 'Nails Assorted 1kg',
                'description' => 'Mixed size common nails',
                'sku' => 'HW-3102',
                'barcode' => '4801234567895',
                'price' => 5500,
                'cost' => 4000,
                'unit' => 'pack',
                'stock' => 85,
                'min_stock' => 20,
                'max_stock' => 150,
            ],
            [
                'category_id' => $hardware?->id,
                'name' => 'Screwdriver Set 6pcs',
                'description' => 'Professional screwdriver set',
                'sku' => 'HW-0902',
                'barcode' => '4801234567896',
                'price' => 26000,
                'cost' => 18000,
                'unit' => 'set',
                'stock' => 18,
                'min_stock' => 5,
                'max_stock' => 40,
            ],
            // Beverages
            [
                'category_id' => $beverages?->id,
                'name' => 'Bottled Water 500ml',
                'description' => 'Purified drinking water',
                'sku' => 'BV-4001',
                'barcode' => '4801234567897',
                'price' => 1500,
                'cost' => 800,
                'unit' => 'bottle',
                'stock' => 200,
                'min_stock' => 50,
                'max_stock' => 500,
            ],
            [
                'category_id' => $beverages?->id,
                'name' => 'Instant Coffee 3-in-1',
                'description' => '20 sachets per pack',
                'sku' => 'BV-4020',
                'barcode' => '4801234567898',
                'price' => 12500,
                'cost' => 9000,
                'unit' => 'pack',
                'stock' => 65,
                'min_stock' => 15,
                'max_stock' => 100,
            ],
            // Household
            [
                'category_id' => $household?->id,
                'name' => 'Laundry Detergent 1kg',
                'description' => 'Powder detergent with fabric conditioner',
                'sku' => 'HH-5001',
                'barcode' => '4801234567899',
                'price' => 15000,
                'cost' => 11000,
                'unit' => 'pack',
                'stock' => 55,
                'min_stock' => 15,
                'max_stock' => 100,
            ],
            [
                'category_id' => $household?->id,
                'name' => 'Dishwashing Liquid 500ml',
                'description' => 'Antibacterial dishwashing liquid',
                'sku' => 'HH-5020',
                'barcode' => '4801234567900',
                'price' => 6500,
                'cost' => 4500,
                'unit' => 'bottle',
                'stock' => 92,
                'min_stock' => 25,
                'max_stock' => 150,
            ],
            // Electronics (if category exists)
            [
                'category_id' => $electronics?->id,
                'name' => 'LED Bulb 9W',
                'description' => 'Energy-saving LED bulb',
                'sku' => 'EL-6001',
                'barcode' => '4801234567901',
                'price' => 9900,
                'cost' => 6500,
                'unit' => 'pc',
                'stock' => 40,
                'min_stock' => 10,
                'max_stock' => 80,
            ],
            [
                'category_id' => $electronics?->id,
                'name' => 'Extension Cord 3m',
                'description' => '4-gang extension with surge protector',
                'sku' => 'EL-6020',
                'barcode' => '4801234567902',
                'price' => 35000,
                'cost' => 25000,
                'unit' => 'pc',
                'stock' => 22,
                'min_stock' => 5,
                'max_stock' => 50,
            ],
        ];

        foreach ($products as $productData) {
            if ($productData['category_id'] === null) {
                continue;
            }

            Product::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'sku' => $productData['sku'],
                ],
                array_merge($productData, [
                    'user_id' => $user->id,
                    'currency' => 'PHP',
                    'is_active' => true,
                ])
            );
        }
    }
}
