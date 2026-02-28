<?php

use App\Models\FoodMenuItem;
use App\Models\FoodMenuReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── List ──────────────────────────────────────────────────────────

it('lists menu items for the authenticated vendor', function () {
    $vendor = User::factory()->vendor()->create();
    $otherVendor = User::factory()->vendor()->create();

    FoodMenuItem::factory()->for($vendor)->count(3)->create();
    FoodMenuItem::factory()->for($otherVendor)->create();

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu');

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
});

it('filters menu items by search term', function () {
    $vendor = User::factory()->vendor()->create();

    FoodMenuItem::factory()->for($vendor)->create(['name' => 'Chicken Adobo']);
    FoodMenuItem::factory()->for($vendor)->create(['name' => 'Pork Sinigang']);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu?search=Chicken');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['name' => 'Chicken Adobo']);
});

it('filters menu items by category', function () {
    $vendor = User::factory()->vendor()->create();

    FoodMenuItem::factory()->for($vendor)->create(['category' => 'Main Course']);
    FoodMenuItem::factory()->for($vendor)->create(['category' => 'Dessert']);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu?category=Dessert');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('filters menu items by availability', function () {
    $vendor = User::factory()->vendor()->create();

    FoodMenuItem::factory()->for($vendor)->create(['is_available' => true]);
    FoodMenuItem::factory()->for($vendor)->unavailable()->create();

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu?is_available=1');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

// ── Create ────────────────────────────────────────────────────────

it('creates a menu item with price converted to cents', function () {
    $vendor = User::factory()->vendor()->create();

    Sanctum::actingAs($vendor);

    $response = $this->postJson('/api/food-menu', [
        'name' => 'Beef Caldereta',
        'description' => 'Hearty beef stew',
        'category' => 'Main Course',
        'price' => 150.50,
        'total_servings' => 30,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment(['name' => 'Beef Caldereta']);
    $response->assertJsonPath('data.price', 150.5);

    $this->assertDatabaseHas('food_menu_items', [
        'user_id' => $vendor->id,
        'name' => 'Beef Caldereta',
        'price' => 15050,
        'total_servings' => 30,
    ]);
});

it('validates required fields when creating a menu item', function () {
    $vendor = User::factory()->vendor()->create();

    Sanctum::actingAs($vendor);

    $response = $this->postJson('/api/food-menu', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'price', 'total_servings']);
});

it('forbids buyers from creating menu items', function () {
    $buyer = User::factory()->buyer()->create();

    Sanctum::actingAs($buyer);

    $response = $this->postJson('/api/food-menu', [
        'name' => 'Test Item',
        'price' => 100,
        'total_servings' => 10,
    ]);

    $response->assertForbidden();
});

// ── Show ──────────────────────────────────────────────────────────

it('shows a menu item with reservations', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create();
    FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
        'servings' => 2,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu/'.$item->id);

    $response->assertSuccessful();
    $response->assertJsonFragment(['id' => $item->id]);
    $response->assertJsonCount(1, 'data.reservations');
});

it('returns 404 when showing another vendor\'s menu item', function () {
    $vendor = User::factory()->vendor()->create();
    $otherVendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($otherVendor)->create();

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu/'.$item->id);

    $response->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────

it('updates a menu item', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create(['price' => 10000]);

    Sanctum::actingAs($vendor);

    $response = $this->putJson('/api/food-menu/'.$item->id, [
        'name' => 'Updated Name',
        'price' => 200,
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Updated Name']);
    $response->assertJsonPath('data.price', 200);

    $this->assertDatabaseHas('food_menu_items', [
        'id' => $item->id,
        'name' => 'Updated Name',
        'price' => 20000,
    ]);
});

it('cannot update another vendor\'s menu item', function () {
    $vendor = User::factory()->vendor()->create();
    $otherVendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($otherVendor)->create();

    Sanctum::actingAs($vendor);

    $response = $this->putJson('/api/food-menu/'.$item->id, [
        'name' => 'Hacked',
    ]);

    $response->assertNotFound();
});

// ── Delete ────────────────────────────────────────────────────────

it('deletes a menu item', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create();

    Sanctum::actingAs($vendor);

    $response = $this->deleteJson('/api/food-menu/'.$item->id);

    $response->assertNoContent();
    $this->assertDatabaseMissing('food_menu_items', ['id' => $item->id]);
});

it('cannot delete another vendor\'s menu item', function () {
    $vendor = User::factory()->vendor()->create();
    $otherVendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($otherVendor)->create();

    Sanctum::actingAs($vendor);

    $response = $this->deleteJson('/api/food-menu/'.$item->id);

    $response->assertNotFound();
    $this->assertDatabaseHas('food_menu_items', ['id' => $item->id]);
});

// ── Public Menu ───────────────────────────────────────────────────

it('shows public menu for a vendor without auth', function () {
    $vendor = User::factory()->vendor()->create();

    FoodMenuItem::factory()->for($vendor)->create([
        'is_available' => true,
        'total_servings' => 20,
        'reserved_servings' => 5,
    ]);
    FoodMenuItem::factory()->for($vendor)->unavailable()->create();
    FoodMenuItem::factory()->for($vendor)->soldOut()->create();

    $response = $this->getJson('/api/food-menu/public/'.$vendor->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('filters public menu by category', function () {
    $vendor = User::factory()->vendor()->create();

    FoodMenuItem::factory()->for($vendor)->create(['category' => 'Main Course', 'total_servings' => 10]);
    FoodMenuItem::factory()->for($vendor)->create(['category' => 'Dessert', 'total_servings' => 10]);

    $response = $this->getJson('/api/food-menu/public/'.$vendor->id.'?category=Dessert');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

// ── Public Reserve ────────────────────────────────────────────────

it('allows public reservation without auth', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 20,
        'reserved_servings' => 5,
    ]);

    $response = $this->postJson('/api/food-menu/public/'.$vendor->id.'/reserve', [
        'food_menu_item_id' => $item->id,
        'customer_name' => 'Juan Dela Cruz',
        'customer_phone' => '09171234567',
        'servings' => 3,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'customer_name' => 'Juan Dela Cruz',
        'servings' => 3,
    ]);

    $this->assertDatabaseHas('food_menu_items', [
        'id' => $item->id,
        'reserved_servings' => 8,
    ]);
});

it('rejects public reservation with insufficient servings', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 10,
        'reserved_servings' => 8,
    ]);

    $response = $this->postJson('/api/food-menu/public/'.$vendor->id.'/reserve', [
        'food_menu_item_id' => $item->id,
        'customer_name' => 'Juan Dela Cruz',
        'customer_phone' => '09171234567',
        'servings' => 5,
    ]);

    $response->assertUnprocessable();
});

it('validates public reservation required fields', function () {
    $vendor = User::factory()->vendor()->create();

    $response = $this->postJson('/api/food-menu/public/'.$vendor->id.'/reserve', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['food_menu_item_id', 'customer_name', 'customer_phone', 'servings']);
});

// ── Auth Required ─────────────────────────────────────────────────

it('requires authentication to access food menu crud', function () {
    $response = $this->getJson('/api/food-menu');

    $response->assertUnauthorized();
});
