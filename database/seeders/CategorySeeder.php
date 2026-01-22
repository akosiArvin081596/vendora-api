<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Groceries',
                'slug' => 'groceries',
                'description' => 'Food items, rice, cooking essentials',
                'icon' => 'shopping-cart',
                'is_active' => true,
            ],
            [
                'name' => 'Hardware',
                'slug' => 'hardware',
                'description' => 'Construction materials, tools, and supplies',
                'icon' => 'wrench',
                'is_active' => true,
            ],
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and accessories',
                'icon' => 'cpu',
                'is_active' => true,
            ],
            [
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General merchandise and miscellaneous items',
                'icon' => 'box',
                'is_active' => true,
            ],
            [
                'name' => 'Beverages',
                'slug' => 'beverages',
                'description' => 'Drinks and refreshments',
                'icon' => 'coffee',
                'is_active' => true,
            ],
            [
                'name' => 'Household',
                'slug' => 'household',
                'description' => 'Cleaning supplies and home essentials',
                'icon' => 'home',
                'is_active' => true,
            ],
            [
                'name' => 'Personal Care',
                'slug' => 'personal-care',
                'description' => 'Hygiene and personal care products',
                'icon' => 'heart',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::query()->updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }
    }
}
