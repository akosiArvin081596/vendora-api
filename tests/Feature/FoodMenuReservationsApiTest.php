<?php

use App\Enums\ReservationStatus;
use App\Models\FoodMenuItem;
use App\Models\FoodMenuReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── List ──────────────────────────────────────────────────────────

it('lists reservations for the authenticated vendor', function () {
    $vendor = User::factory()->vendor()->create();
    $otherVendor = User::factory()->vendor()->create();

    $item = FoodMenuItem::factory()->for($vendor)->create();
    FoodMenuReservation::factory()->count(2)->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
    ]);

    $otherItem = FoodMenuItem::factory()->for($otherVendor)->create();
    FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $otherItem->id,
        'user_id' => $otherVendor->id,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu-reservations');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
});

it('filters reservations by status', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create();

    FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
        'status' => ReservationStatus::Pending,
    ]);
    FoodMenuReservation::factory()->confirmed()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu-reservations?status=pending');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('filters reservations by menu item', function () {
    $vendor = User::factory()->vendor()->create();
    $item1 = FoodMenuItem::factory()->for($vendor)->create();
    $item2 = FoodMenuItem::factory()->for($vendor)->create();

    FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item1->id,
        'user_id' => $vendor->id,
    ]);
    FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item2->id,
        'user_id' => $vendor->id,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu-reservations?food_menu_item_id='.$item1->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

// ── Create ────────────────────────────────────────────────────────

it('creates a reservation and increments reserved servings', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 20,
        'reserved_servings' => 5,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->postJson('/api/food-menu-reservations', [
        'food_menu_item_id' => $item->id,
        'customer_name' => 'Maria Santos',
        'customer_phone' => '09181234567',
        'servings' => 3,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'customer_name' => 'Maria Santos',
        'servings' => 3,
    ]);

    $this->assertDatabaseHas('food_menu_items', [
        'id' => $item->id,
        'reserved_servings' => 8,
    ]);
});

it('rejects reservation when insufficient servings', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 10,
        'reserved_servings' => 9,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->postJson('/api/food-menu-reservations', [
        'food_menu_item_id' => $item->id,
        'customer_name' => 'Test Customer',
        'servings' => 5,
    ]);

    $response->assertUnprocessable();
});

it('rejects reservation for another vendor\'s menu item', function () {
    $vendor = User::factory()->vendor()->create();
    $otherVendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($otherVendor)->create([
        'total_servings' => 20,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->postJson('/api/food-menu-reservations', [
        'food_menu_item_id' => $item->id,
        'customer_name' => 'Test Customer',
        'servings' => 1,
    ]);

    $response->assertNotFound();
});

// ── Show ──────────────────────────────────────────────────────────

it('shows a reservation', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create();
    $reservation = FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu-reservations/'.$reservation->id);

    $response->assertSuccessful();
    $response->assertJsonFragment(['id' => $reservation->id]);
});

it('cannot show another vendor\'s reservation', function () {
    $vendor = User::factory()->vendor()->create();
    $otherVendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($otherVendor)->create();
    $reservation = FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $otherVendor->id,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/food-menu-reservations/'.$reservation->id);

    $response->assertNotFound();
});

// ── Update Status ─────────────────────────────────────────────────

it('cancelling a reservation decrements reserved servings', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 20,
        'reserved_servings' => 10,
    ]);
    $reservation = FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
        'servings' => 3,
        'status' => ReservationStatus::Pending,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->patchJson('/api/food-menu-reservations/'.$reservation->id, [
        'status' => 'cancelled',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('food_menu_items', [
        'id' => $item->id,
        'reserved_servings' => 7,
    ]);
});

it('updating servings adjusts reserved servings delta', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 20,
        'reserved_servings' => 10,
    ]);
    $reservation = FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
        'servings' => 3,
        'status' => ReservationStatus::Pending,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->patchJson('/api/food-menu-reservations/'.$reservation->id, [
        'servings' => 5,
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('food_menu_items', [
        'id' => $item->id,
        'reserved_servings' => 12,
    ]);
});

it('rejects servings increase when insufficient remaining', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 10,
        'reserved_servings' => 9,
    ]);
    $reservation = FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
        'servings' => 2,
        'status' => ReservationStatus::Pending,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->patchJson('/api/food-menu-reservations/'.$reservation->id, [
        'servings' => 10,
    ]);

    $response->assertUnprocessable();
});

// ── Delete ────────────────────────────────────────────────────────

it('deleting a reservation decrements reserved servings', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 20,
        'reserved_servings' => 10,
    ]);
    $reservation = FoodMenuReservation::factory()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
        'servings' => 3,
        'status' => ReservationStatus::Pending,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->deleteJson('/api/food-menu-reservations/'.$reservation->id);

    $response->assertNoContent();

    $this->assertDatabaseHas('food_menu_items', [
        'id' => $item->id,
        'reserved_servings' => 7,
    ]);

    $this->assertDatabaseMissing('food_menu_reservations', ['id' => $reservation->id]);
});

it('deleting a cancelled reservation does not change reserved servings', function () {
    $vendor = User::factory()->vendor()->create();
    $item = FoodMenuItem::factory()->for($vendor)->create([
        'total_servings' => 20,
        'reserved_servings' => 10,
    ]);
    $reservation = FoodMenuReservation::factory()->cancelled()->create([
        'food_menu_item_id' => $item->id,
        'user_id' => $vendor->id,
        'servings' => 3,
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->deleteJson('/api/food-menu-reservations/'.$reservation->id);

    $response->assertNoContent();

    $this->assertDatabaseHas('food_menu_items', [
        'id' => $item->id,
        'reserved_servings' => 10,
    ]);
});

// ── Auth Required ─────────────────────────────────────────────────

it('requires authentication to access reservations', function () {
    $response = $this->getJson('/api/food-menu-reservations');

    $response->assertUnauthorized();
});
