<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists inventory items for the authenticated user', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product = Product::factory()->for($user)->for($category)->create();
    Product::factory()->for($otherUser)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/inventory');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'id' => $product->id,
        'name' => $product->name,
        'sku' => $product->sku,
    ]);
});

it('filters inventory by search term', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product1 = Product::factory()->for($user)->for($category)->create(['name' => 'Premium Rice']);
    Product::factory()->for($user)->for($category)->create(['name' => 'Cooking Oil']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/inventory?search=Rice');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
});

it('filters inventory by sku', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product1 = Product::factory()->for($user)->for($category)->create(['sku' => 'ABC-123']);
    Product::factory()->for($user)->for($category)->create(['sku' => 'XYZ-789']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/inventory?search=ABC');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
});

it('filters inventory by in_stock status', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $product1 = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'min_stock' => 10,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'stock' => 5,
        'min_stock' => 10,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'stock' => 0,
        'min_stock' => 10,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/inventory?status=in_stock');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product1->id]);
});

it('filters inventory by low_stock status', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'min_stock' => 10,
    ]);
    $product2 = Product::factory()->for($user)->for($category)->create([
        'stock' => 5,
        'min_stock' => 10,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'stock' => 0,
        'min_stock' => 10,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/inventory?status=low_stock');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product2->id]);
});

it('filters inventory by out_of_stock status', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'min_stock' => 10,
    ]);
    Product::factory()->for($user)->for($category)->create([
        'stock' => 5,
        'min_stock' => 10,
    ]);
    $product3 = Product::factory()->for($user)->for($category)->create([
        'stock' => 0,
        'min_stock' => 10,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/inventory?status=out_of_stock');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $product3->id]);
});

it('returns inventory summary', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Product::factory()->for($user)->for($category)->count(5)->create([
        'stock' => 50,
        'min_stock' => 10,
    ]);
    Product::factory()->for($user)->for($category)->count(3)->create([
        'stock' => 5,
        'min_stock' => 10,
    ]);
    Product::factory()->for($user)->for($category)->count(2)->create([
        'stock' => 0,
        'min_stock' => 10,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/inventory/summary');

    $response->assertSuccessful();
    $response->assertJsonPath('data.total_items', 10);
    $response->assertJsonPath('data.low_stock_items', 3);
    $response->assertJsonPath('data.out_of_stock_items', 2);
});

it('forbids non-vendors from adjusting inventory', function () {
    $user = User::factory()->buyer()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 10]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 5,
    ]);

    $response->assertForbidden();
    expect($product->fresh()->stock)->toBe(10);
});

it('adds stock via adjustment', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 10]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 5,
        'note' => 'Restocking',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('message', 'Stock adjusted successfully.');
    $response->assertJsonPath('inventory.stock', 15);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 15,
    ]);

    $this->assertDatabaseHas('inventory_adjustments', [
        'user_id' => $user->id,
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 5,
        'stock_before' => 10,
        'stock_after' => 15,
        'note' => 'Restocking',
    ]);
});

it('removes stock via adjustment', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 20]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 8,
        'note' => 'Damaged items',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('inventory.stock', 12);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 12,
    ]);

    $this->assertDatabaseHas('inventory_adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 8,
        'stock_before' => 20,
        'stock_after' => 12,
    ]);
});

it('sets stock via adjustment', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 50]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'set',
        'quantity' => 25,
        'note' => 'Physical count correction',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('inventory.stock', 25);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 25,
    ]);

    $this->assertDatabaseHas('inventory_adjustments', [
        'product_id' => $product->id,
        'type' => 'set',
        'quantity' => 25,
        'stock_before' => 50,
        'stock_after' => 25,
    ]);
});

it('validates required fields when creating adjustment', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_id', 'type', 'quantity']);
});

it('validates type is valid', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'invalid',
        'quantity' => 5,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['type']);
});

it('prevents removing more than current stock', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 5]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 10,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['quantity']);
});

it('cannot adjust another users product', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($otherUser)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 5,
    ]);

    $response->assertNotFound();
});

it('requires authentication to access inventory', function () {
    $response = $this->getJson('/api/inventory');

    $response->assertUnauthorized();
});
