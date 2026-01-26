<?php

use App\Models\Category;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds product categories in the database seeder', function () {
    $this->seed(DatabaseSeeder::class);

    $expectedSlugs = [
        'groceries',
        'hardware',
        'electronics',
        'general',
        'beverages',
        'household',
        'personal-care',
    ];

    $count = Category::query()
        ->whereIn('slug', $expectedSlugs)
        ->count();

    expect($count)->toBe(count($expectedSlugs));
});
