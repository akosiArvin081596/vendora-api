<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('gets a product by SKU', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'sku' => 'TST-001',
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/sku/TST-001');

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $product->id,
        'sku' => 'TST-001',
    ]);
})->skip('Public product endpoints temporarily disabled');

it('returns 404 for non-existent SKU', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/sku/NONEXISTENT');

    $response->assertNotFound();
});

it('allows public access to any product by SKU', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($otherUser)->for($category)->create([
        'sku' => 'OTHER-001',
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    // Public endpoint - no auth required, can view any active e-commerce product
    $response = $this->getJson('/api/products/sku/OTHER-001');

    $response->assertSuccessful();
    $response->assertJsonFragment(['sku' => 'OTHER-001']);
})->skip('Public product endpoints temporarily disabled');

it('gets a product by barcode', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'barcode' => '4801234567890',
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/barcode/4801234567890');

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $product->id,
        'barcode' => '4801234567890',
    ]);
})->skip('Public product endpoints temporarily disabled');

it('returns 404 for non-existent barcode', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/barcode/9999999999999');

    $response->assertNotFound();
});

it('updates product stock directly', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 50]);

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/products/'.$product->id.'/stock', [
        'stock' => 100,
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'stock' => 100,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 100,
    ]);
});

it('validates stock update requires positive integer', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/products/'.$product->id.'/stock', [
        'stock' => -10,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['stock']);
});

it('decrements stock for multiple products in bulk', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product1 = Product::factory()->for($user)->for($category)->create(['stock' => 100]);
    $product2 = Product::factory()->for($user)->for($category)->create(['stock' => 50]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products/bulk-stock-decrement', [
        'items' => [
            ['productId' => $product1->id, 'quantity' => 10],
            ['productId' => $product2->id, 'quantity' => 5],
        ],
        'orderId' => 'ORD-001',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'data' => [
            'updated' => [
                ['productId' => $product1->id, 'newStock' => 90],
                ['productId' => $product2->id, 'newStock' => 45],
            ],
            'errors' => [],
        ],
    ]);

    $this->assertDatabaseHas('products', ['id' => $product1->id, 'stock' => 90]);
    $this->assertDatabaseHas('products', ['id' => $product2->id, 'stock' => 45]);
});

it('returns error for insufficient stock in bulk decrement', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 5]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products/bulk-stock-decrement', [
        'items' => [
            ['productId' => $product->id, 'quantity' => 10],
        ],
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => false,
    ]);

    $errors = $response->json('data.errors');
    expect($errors)->toHaveCount(1);
    expect($errors[0]['error'])->toBe('Insufficient stock');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
});

it('filters products by stock_lte', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    Product::factory()->for($user)->for($category)->create([
        'stock' => 5,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?stock_lte=10');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
})->skip('Public product endpoints temporarily disabled');

it('filters products by stock_gte', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    Product::factory()->for($user)->for($category)->create([
        'stock' => 5,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?stock_gte=40');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
})->skip('Public product endpoints temporarily disabled');

it('filters products by has_barcode', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    Product::factory()->for($user)->for($category)->create(['barcode' => '1234567890123']);
    Product::factory()->for($user)->for($category)->create(['barcode' => null]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?has_barcode=true');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
})->skip('Public product endpoints temporarily disabled');

it('filters products by is_active', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    Product::factory()->for($user)->for($category)->create(['is_active' => true]);
    Product::factory()->for($user)->for($category)->create(['is_active' => false]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?is_active=true');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
})->skip('Public product endpoints temporarily disabled');

it('filters products by category slug', function () {
    $user = User::factory()->vendor()->create();
    $category1 = Category::factory()->create(['slug' => 'electronics']);
    $category2 = Category::factory()->create(['slug' => 'groceries']);
    $product1 = Product::factory()->for($user)->for($category1)->create([
        'is_active' => true,
        'is_ecommerce' => true,
    ]);
    Product::factory()->for($user)->for($category2)->create([
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?category=electronics');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
})->skip('Public product endpoints temporarily disabled');

it('returns new product fields in response', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'description' => 'Test description',
        'barcode' => '1234567890123',
        'cost' => 500000, // Stored in cents (5000 * 100)
        'unit' => 'kg',
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/'.$product->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'description' => 'Test description',
        'barcode' => '1234567890123',
        'has_barcode' => true,
        'cost' => 5000, // Resource converts from cents
        'unit' => 'kg',
        'is_active' => true,
        'is_ecommerce' => true,
    ]);
})->skip('Public product endpoints temporarily disabled');

it('creates product with new fields', function () {
    Storage::fake('public');

    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'New Product',
        'description' => 'Product description',
        'sku' => 'NEW-001',
        'barcode' => '9876543210123',
        'category_id' => $category->id,
        'price' => 10000,
        'cost' => 7500,
        'currency' => 'PHP',
        'unit' => 'pack',
        'stock' => 25,
        'min_stock' => 5,
        'max_stock' => 50,
        'is_active' => true,
        'is_ecommerce' => false,
        'image' => UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'description' => 'Product description',
        'barcode' => '9876543210123',
        'cost' => 7500,
        'unit' => 'pack',
        'is_active' => true,
        'is_ecommerce' => false,
    ]);

    $this->assertDatabaseHas('products', [
        'sku' => 'NEW-001',
        'barcode' => '9876543210123',
        'cost' => 750000, // Stored in cents (7500 * 100)
        'unit' => 'pack',
        'is_ecommerce' => false,
    ]);
});

it('soft deletes products', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/products/'.$product->id);

    $response->assertNoContent();

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});
