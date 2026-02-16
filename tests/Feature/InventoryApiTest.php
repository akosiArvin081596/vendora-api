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

    // Validation rejects the product as invalid (not accessible to this user)
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_id']);
});

it('requires authentication to access inventory', function () {
    $response = $this->getJson('/api/inventory');

    $response->assertUnauthorized();
});

it('adds stock with unit cost and updates product cost', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 10,
        'cost' => 5000, // 50.00 in cents
        'price' => 7500, // 75.00 in cents
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 50,
        'unit_cost' => 55.00,
        'note' => 'New batch at higher price',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('inventory.stock', 60);

    // Product cost should be updated to the new unit cost
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 60,
        'cost' => 5500, // 55.00 in cents
    ]);

    // Adjustment should record unit_cost
    $this->assertDatabaseHas('inventory_adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 50,
        'stock_before' => 10,
        'stock_after' => 60,
        'unit_cost' => 5500,
    ]);

    // Ledger entry should have amount calculated from unit cost
    $this->assertDatabaseHas('ledger_entries', [
        'product_id' => $product->id,
        'type' => 'stock_in',
        'quantity' => 50,
        'amount' => 275000, // 55.00 * 50 = 2750.00 in cents
        'balance_qty' => 60,
    ]);
});

it('adds stock without unit cost and uses existing product cost for ledger', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 10,
        'cost' => 5000,
        'price' => 7500,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 20,
    ]);

    $response->assertCreated();

    // Product cost should NOT change
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'cost' => 5000,
    ]);

    // Ledger entry should use existing product cost
    $this->assertDatabaseHas('ledger_entries', [
        'product_id' => $product->id,
        'type' => 'stock_in',
        'quantity' => 20,
        'amount' => 100000, // 50.00 * 20 = 1000.00 in cents
    ]);
});

it('returns correct stock after a failed removal followed by a successful one', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 12,
        'cost' => 5000,
    ]);

    Sanctum::actingAs($user);

    // Step 1: Try to remove more than available — should fail
    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 13,
    ]);

    $response->assertUnprocessable();
    expect($product->fresh()->stock)->toBe(12);

    // Step 2: Remove a valid quantity — should succeed
    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 5,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('inventory.stock', 7);
    expect($product->fresh()->stock)->toBe(7);

    // Step 3: Add stock — should compute from the correct base (7)
    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 3,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('inventory.stock', 10);
    expect($product->fresh()->stock)->toBe(10);
});

it('does not update product cost when removing stock with unit cost', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 30,
        'cost' => 5000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 5,
        'unit_cost' => 60.00,
    ]);

    $response->assertCreated();

    // Cost should remain unchanged on remove
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'cost' => 5000,
    ]);
});
