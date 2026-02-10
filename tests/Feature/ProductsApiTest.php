<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists products filtered by user_id', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product = Product::factory()->for($user)->for($category)->create(['is_active' => true]);
    Product::factory()->for($otherUser)->for($category)->create(['is_active' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?user_id='.$user->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'id' => $product->id,
        'name' => $product->name,
        'sku' => $product->sku,
    ]);
})->skip('Public product endpoints temporarily disabled');

it('filters products by search term', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product1 = Product::factory()->for($user)->for($category)->create([
        'name' => 'Apple iPhone',
        'is_active' => true,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'name' => 'Samsung Galaxy',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?search=iPhone&user_id='.$user->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
})->skip('Public product endpoints temporarily disabled');

it('filters products by category', function () {
    $user = User::factory()->vendor()->create();
    $category1 = Category::factory()->create(['name' => 'Electronics']);
    $category2 = Category::factory()->create(['name' => 'Groceries']);

    $product1 = Product::factory()->for($user)->for($category1)->create(['is_active' => true]);
    Product::factory()->for($user)->for($category2)->create(['is_active' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?category_id='.$category1->id.'&user_id='.$user->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
})->skip('Public product endpoints temporarily disabled');

it('filters products by price range', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product1 = Product::factory()->for($user)->for($category)->create(['price' => 500, 'is_active' => true]);
    Product::factory()->for($user)->for($category)->create(['price' => 100, 'is_active' => true]);
    Product::factory()->for($user)->for($category)->create(['price' => 2000, 'is_active' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?min_price=400&max_price=600&user_id='.$user->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
})->skip('Public product endpoints temporarily disabled');

it('filters products by in stock status', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product1 = Product::factory()->for($user)->for($category)->create(['stock' => 10, 'is_active' => true]);
    Product::factory()->for($user)->for($category)->create(['stock' => 0, 'is_active' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products?in_stock=true&user_id='.$user->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
})->skip('Public product endpoints temporarily disabled');

it('creates a product', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'Test Product',
        'sku' => 'TST-001',
        'category_id' => $category->id,
        'price' => 1500,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 50,
        'min_stock' => 10,
        'max_stock' => 100,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'Test Product',
        'sku' => 'TST-001',
        'price' => 1500,
    ]);

    $this->assertDatabaseHas('products', [
        'user_id' => $user->id,
        'name' => 'Test Product',
        'sku' => 'TST-001',
    ]);
});

it('allows admins to create a product', function () {
    $user = User::factory()->admin()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'Admin Product',
        'sku' => 'ADM-001',
        'category_id' => $category->id,
        'price' => 2500,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 25,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'Admin Product',
        'sku' => 'ADM-001',
        'price' => 2500,
    ]);

    $this->assertDatabaseHas('products', [
        'user_id' => $user->id,
        'name' => 'Admin Product',
        'sku' => 'ADM-001',
    ]);
});

it('forbids non-vendors from creating a product', function () {
    $user = User::factory()->buyer()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'Test Product',
        'sku' => 'TST-002',
        'category_id' => $category->id,
        'price' => 1500,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 50,
        'min_stock' => 10,
        'max_stock' => 100,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    $response->assertForbidden();
});

it('validates required fields when creating a product', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors([
        'name',
        'sku',
        'category_id',
        'price',
        'currency',
        'unit',
        'stock',
        'is_active',
        'is_ecommerce',
    ]);
});

it('prevents duplicate SKU for the same user', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Product::factory()->for($user)->for($category)->create(['sku' => 'DUPLICATE']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'Another Product',
        'sku' => 'DUPLICATE',
        'category_id' => $category->id,
        'price' => 100,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 10,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['sku']);
});

it('shows an active product', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['is_active' => true]);

    $response = $this->getJson('/api/products/'.$product->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $product->id,
        'name' => $product->name,
        'sku' => $product->sku,
    ]);
})->skip('Public product endpoints temporarily disabled');

it('returns 404 for non-existent product', function () {
    $response = $this->getJson('/api/products/99999');

    $response->assertNotFound();
})->skip('Public product endpoints temporarily disabled');

it('returns 404 for inactive product on public show', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['is_active' => false]);

    $response = $this->getJson('/api/products/'.$product->id);

    $response->assertNotFound();
})->skip('Public product endpoints temporarily disabled');

it('updates a product', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/products/'.$product->id, [
        'name' => 'Updated Product Name',
        'price' => 2000,
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'Updated Product Name',
        'price' => 2000,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Product Name',
        'price' => 200000, // Stored in cents
    ]);
});

it('cannot update another users product', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($otherUser)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/products/'.$product->id, [
        'name' => 'Hacked Name',
    ]);

    $response->assertNotFound();
});

it('deletes a product', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/products/'.$product->id);

    $response->assertNoContent();

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

it('cannot delete another users product', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($otherUser)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/products/'.$product->id);

    $response->assertNotFound();

    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

it('allows public access to products list', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    Product::factory()->for($user)->for($category)->create(['is_active' => true]);

    $response = $this->getJson('/api/products');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
})->skip('Public product endpoints temporarily disabled');

it('returns only authenticated users products on /products/my endpoint', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $myProduct1 = Product::factory()->for($user)->for($category)->create(['is_active' => true]);
    $myProduct2 = Product::factory()->for($user)->for($category)->create(['is_active' => true]);
    Product::factory()->for($otherUser)->for($category)->create(['is_active' => true]);
    Product::factory()->for($otherUser)->for($category)->create(['is_active' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/my');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['id' => $myProduct1->id]);
    $response->assertJsonFragment(['id' => $myProduct2->id]);
});

it('requires authentication for /products/my endpoint', function () {
    $response = $this->getJson('/api/products/my');

    $response->assertUnauthorized();
});

it('filters my products by search term', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product1 = Product::factory()->for($user)->for($category)->create([
        'name' => 'Apple iPhone',
        'is_active' => true,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'name' => 'Samsung Galaxy',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/my?search=iPhone');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
});

it('filters my products by category', function () {
    $user = User::factory()->vendor()->create();
    $category1 = Category::factory()->create(['name' => 'Electronics']);
    $category2 = Category::factory()->create(['name' => 'Groceries']);

    $product1 = Product::factory()->for($user)->for($category1)->create(['is_active' => true]);
    Product::factory()->for($user)->for($category2)->create(['is_active' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/my?category_id='.$category1->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
});

it('includes inactive products on /products/my endpoint', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $activeProduct = Product::factory()->for($user)->for($category)->create(['is_active' => true]);
    $inactiveProduct = Product::factory()->for($user)->for($category)->create(['is_active' => false]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/my');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['id' => $activeProduct->id]);
    $response->assertJsonFragment(['id' => $inactiveProduct->id]);
});

it('can filter my products by is_active status', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $activeProduct = Product::factory()->for($user)->for($category)->create(['is_active' => true]);
    Product::factory()->for($user)->for($category)->create(['is_active' => false]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/my?is_active=true');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $activeProduct->id]);
});
