<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Store CRUD Tests

it('lists stores owned by the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $store = Store::factory()->for($user)->create();
    Store::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/stores');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'id' => $store->id,
        'name' => $store->name,
    ]);
});

it('lists stores user is assigned to as staff', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $ownedStore = Store::factory()->for($user)->create();
    $assignedStore = Store::factory()->for($owner)->create();

    $assignedStore->staff()->attach($user->id, [
        'role' => 'manager',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/stores');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
});

it('creates a store', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stores', [
        'name' => 'New Store',
        'code' => 'NEW-001',
        'address' => '123 Main Street',
        'phone' => '+63 912 345 6789',
        'email' => 'store@test.com',
        'is_active' => true,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'New Store',
        'code' => 'NEW-001',
    ]);

    $this->assertDatabaseHas('stores', [
        'user_id' => $user->id,
        'name' => 'New Store',
        'code' => 'NEW-001',
    ]);
});

it('validates required fields when creating a store', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stores', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'code']);
});

it('shows a store owned by user', function () {
    $user = User::factory()->create();
    $store = Store::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/stores/'.$store->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $store->id,
        'name' => $store->name,
    ]);
});

it('shows a store user is assigned to', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($staff);

    $response = $this->getJson('/api/stores/'.$store->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $store->id,
        'name' => $store->name,
    ]);
});

it('cannot view store user has no access to', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $store = Store::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/stores/'.$store->id);

    $response->assertForbidden();
});

it('updates a store as owner', function () {
    $user = User::factory()->create();
    $store = Store::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/stores/'.$store->id, [
        'name' => 'Updated Store Name',
        'is_active' => false,
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'Updated Store Name',
        'is_active' => false,
    ]);

    $this->assertDatabaseHas('stores', [
        'id' => $store->id,
        'name' => 'Updated Store Name',
        'is_active' => false,
    ]);
});

it('updates a store as manager', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($manager->id, [
        'role' => 'manager',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($manager);

    $response = $this->patchJson('/api/stores/'.$store->id, [
        'name' => 'Manager Updated Name',
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'Manager Updated Name',
    ]);
});

it('cannot update store as cashier', function () {
    $owner = User::factory()->create();
    $cashier = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($cashier->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($cashier);

    $response = $this->patchJson('/api/stores/'.$store->id, [
        'name' => 'Unauthorized Update',
    ]);

    $response->assertForbidden();
});

it('deletes a store as owner', function () {
    $user = User::factory()->create();
    $store = Store::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/stores/'.$store->id);

    $response->assertNoContent();

    $this->assertDatabaseMissing('stores', ['id' => $store->id]);
});

it('cannot delete store as manager', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($manager->id, [
        'role' => 'manager',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($manager);

    $response = $this->deleteJson('/api/stores/'.$store->id);

    $response->assertForbidden();

    $this->assertDatabaseHas('stores', ['id' => $store->id]);
});

// Staff Management Tests

it('lists staff members of a store', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create(['name' => 'Staff Member']);
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/stores/'.$store->id.'/staff');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'id' => $staff->id,
        'name' => 'Staff Member',
        'role' => 'cashier',
    ]);
});

it('adds staff to a store', function () {
    $owner = User::factory()->create();
    $newStaff = User::factory()->create(['email' => 'staff@test.com']);
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/stores/'.$store->id.'/staff', [
        'email' => 'staff@test.com',
        'role' => 'manager',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'id' => $newStaff->id,
        'role' => 'manager',
    ]);

    $this->assertDatabaseHas('store_user', [
        'store_id' => $store->id,
        'user_id' => $newStaff->id,
        'role' => 'manager',
    ]);
});

it('cannot add owner as staff', function () {
    $owner = User::factory()->create(['email' => 'owner@test.com']);
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/stores/'.$store->id.'/staff', [
        'email' => 'owner@test.com',
        'role' => 'cashier',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonFragment([
        'message' => 'Cannot add store owner as staff.',
    ]);
});

it('cannot add same user twice to store', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create(['email' => 'staff@test.com']);
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/stores/'.$store->id.'/staff', [
        'email' => 'staff@test.com',
        'role' => 'manager',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonFragment([
        'message' => 'User is already a staff member of this store.',
    ]);
});

it('updates staff role', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $response = $this->patchJson('/api/stores/'.$store->id.'/staff/'.$staff->id, [
        'role' => 'manager',
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'role' => 'manager',
    ]);

    $this->assertDatabaseHas('store_user', [
        'store_id' => $store->id,
        'user_id' => $staff->id,
        'role' => 'manager',
    ]);
});

it('removes staff from store', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $response = $this->deleteJson('/api/stores/'.$store->id.'/staff/'.$staff->id);

    $response->assertNoContent();

    $this->assertDatabaseMissing('store_user', [
        'store_id' => $store->id,
        'user_id' => $staff->id,
    ]);
});

it('returns available roles', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/store-roles');

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonFragment(['value' => 'manager']);
    $response->assertJsonFragment(['value' => 'cashier']);
    $response->assertJsonFragment(['value' => 'staff']);
});

// Store Products Tests

it('lists products at a store', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $product = Product::factory()->for($owner)->for($category)->create(['is_active' => true]);

    $storeProduct = StoreProduct::factory()
        ->for($store)
        ->for($product)
        ->create(['stock' => 50]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/stores/'.$store->id.'/products');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'product_id' => $product->id,
        'stock' => 50,
    ]);
});

it('adds a product to store', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $product = Product::factory()->for($owner)->for($category)->create(['is_active' => true]);

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/stores/'.$store->id.'/products', [
        'product_id' => $product->id,
        'stock' => 100,
        'min_stock' => 10,
        'max_stock' => 200,
        'is_available' => true,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'product_id' => $product->id,
        'stock' => 100,
    ]);

    $this->assertDatabaseHas('store_products', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'stock' => 100,
    ]);
});

it('cannot add product that does not belong to owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $product = Product::factory()->for($otherUser)->for($category)->create(['is_active' => true]);

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/stores/'.$store->id.'/products', [
        'product_id' => $product->id,
        'stock' => 50,
    ]);

    $response->assertNotFound();
});

it('updates store product', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $product = Product::factory()->for($owner)->for($category)->create(['is_active' => true]);

    StoreProduct::factory()
        ->for($store)
        ->for($product)
        ->create(['stock' => 50]);

    Sanctum::actingAs($owner);

    $response = $this->patchJson('/api/stores/'.$store->id.'/products/'.$product->id, [
        'stock' => 75,
        'is_available' => false,
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'stock' => 75,
        'is_available' => false,
    ]);

    $this->assertDatabaseHas('store_products', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'stock' => 75,
        'is_available' => false,
    ]);
});

it('removes product from store', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $product = Product::factory()->for($owner)->for($category)->create(['is_active' => true]);

    StoreProduct::factory()
        ->for($store)
        ->for($product)
        ->create();

    Sanctum::actingAs($owner);

    $response = $this->deleteJson('/api/stores/'.$store->id.'/products/'.$product->id);

    $response->assertNoContent();

    $this->assertDatabaseMissing('store_products', [
        'store_id' => $store->id,
        'product_id' => $product->id,
    ]);
});

it('requires authentication to access stores', function () {
    $response = $this->getJson('/api/stores');

    $response->assertUnauthorized();
});
