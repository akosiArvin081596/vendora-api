<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Set updated_at directly via query builder to bypass Eloquent auto-timestamps.
 */
function forceUpdatedAt(string $table, int $id, string $timestamp): void
{
    DB::table($table)->where('id', $id)->update(['updated_at' => $timestamp]);
}

// --- Products (GET /products/my) ---

it('returns all products when updated_since is not provided', function () {
    $user = User::factory()->vendor()->create();
    Product::factory()->count(3)->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/products/my?per_page=100');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(3);
});

it('filters products by updated_since', function () {
    $user = User::factory()->vendor()->create();

    $old = Product::factory()->for($user)->create();
    forceUpdatedAt('products', $old->id, now()->subDays(2)->toDateTimeString());

    $recent = Product::factory()->for($user)->create();
    forceUpdatedAt('products', $recent->id, now()->subHour()->toDateTimeString());

    Sanctum::actingAs($user);

    $since = now()->subHours(2)->toIso8601String();
    $response = $this->getJson("/api/products/my?per_page=100&updated_since={$since}");

    $response->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($recent->id);
    expect($ids)->not->toContain($old->id);
});

it('includes soft-deleted products when include_deleted is true', function () {
    $user = User::factory()->vendor()->create();

    $active = Product::factory()->for($user)->create();
    $deleted = Product::factory()->for($user)->create();
    $deleted->delete();

    Sanctum::actingAs($user);

    // Without include_deleted
    $response = $this->getJson('/api/products/my?per_page=100');
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($active->id);
    expect($ids)->not->toContain($deleted->id);

    // With include_deleted
    $response = $this->getJson('/api/products/my?per_page=100&include_deleted=true');
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($active->id);
    expect($ids)->toContain($deleted->id);
});

// --- Categories (GET /categories) ---

it('filters categories by updated_since', function () {
    $user = User::factory()->vendor()->create();

    $old = Category::factory()->create();
    forceUpdatedAt('categories', $old->id, now()->subDays(2)->toDateTimeString());

    $recent = Category::factory()->create();
    forceUpdatedAt('categories', $recent->id, now()->subHour()->toDateTimeString());

    Sanctum::actingAs($user);

    $since = now()->subHours(2)->toIso8601String();
    $response = $this->getJson("/api/categories?updated_since={$since}");

    $response->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($recent->id);
    expect($ids)->not->toContain($old->id);
});

// --- Customers (GET /customers) ---

it('filters customers by updated_since', function () {
    $user = User::factory()->vendor()->create();

    $old = Customer::factory()->for($user)->create();
    forceUpdatedAt('customers', $old->id, now()->subDays(2)->toDateTimeString());

    $recent = Customer::factory()->for($user)->create();
    forceUpdatedAt('customers', $recent->id, now()->subHour()->toDateTimeString());

    Sanctum::actingAs($user);

    $since = now()->subHours(2)->toIso8601String();
    $response = $this->getJson("/api/customers?updated_since={$since}");

    $response->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($recent->id);
    expect($ids)->not->toContain($old->id);
});

// --- Orders (GET /orders) ---

it('filters orders by updated_since', function () {
    $user = User::factory()->vendor()->create();
    $customer = Customer::factory()->for($user)->create();

    $old = Order::factory()->for($user)->for($customer)->create();
    forceUpdatedAt('orders', $old->id, now()->subDays(2)->toDateTimeString());

    $recent = Order::factory()->for($user)->for($customer)->create();
    forceUpdatedAt('orders', $recent->id, now()->subHour()->toDateTimeString());

    Sanctum::actingAs($user);

    $since = now()->subHours(2)->toIso8601String();
    $response = $this->getJson("/api/orders?updated_since={$since}");

    $response->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($recent->id);
    expect($ids)->not->toContain($old->id);
});

// --- Inventory (GET /inventory) ---

it('filters inventory by updated_since', function () {
    $user = User::factory()->vendor()->create();

    $old = Product::factory()->for($user)->create(['stock' => 10]);
    forceUpdatedAt('products', $old->id, now()->subDays(2)->toDateTimeString());

    $recent = Product::factory()->for($user)->create(['stock' => 5]);
    forceUpdatedAt('products', $recent->id, now()->subHour()->toDateTimeString());

    Sanctum::actingAs($user);

    $since = now()->subHours(2)->toIso8601String();
    $response = $this->getJson("/api/inventory?updated_since={$since}");

    $response->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($recent->id);
    expect($ids)->not->toContain($old->id);
});
