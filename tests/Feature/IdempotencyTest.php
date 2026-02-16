<?php

use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('passes through GET requests without idempotency key', function () {
    $user = User::factory()->vendor()->create();
    Category::factory()->count(2)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/categories');

    $response->assertSuccessful();
    $this->assertDatabaseCount('idempotency_keys', 0);
});

it('passes through POST requests without idempotency key header', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/categories', [
        'name' => 'Test Category',
    ]);

    $response->assertCreated();
    $this->assertDatabaseCount('idempotency_keys', 0);
});

it('stores response for POST request with idempotency key', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $key = fake()->uuid();

    $response = $this->postJson('/api/categories', [
        'name' => 'Electronics',
    ], ['X-Idempotency-Key' => $key]);

    $response->assertCreated();

    $this->assertDatabaseHas('idempotency_keys', [
        'key' => $key,
        'user_id' => $user->id,
        'http_method' => 'POST',
        'status_code' => 201,
    ]);
});

it('returns cached response for duplicate POST with same idempotency key', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $key = fake()->uuid();

    // First request
    $first = $this->postJson('/api/categories', [
        'name' => 'Electronics',
    ], ['X-Idempotency-Key' => $key]);

    $first->assertCreated();

    // Second (duplicate) request with same key
    $second = $this->postJson('/api/categories', [
        'name' => 'Electronics',
    ], ['X-Idempotency-Key' => $key]);

    // Should return the cached response, not 422 (duplicate name)
    $second->assertCreated();
    $second->assertHeader('X-Idempotent-Replayed', 'true');

    // Only one category should exist
    $this->assertDatabaseCount('categories', 1);
});

it('allows same idempotency key for different users', function () {
    $userA = User::factory()->vendor()->create();
    $userB = User::factory()->vendor()->create();

    $key = fake()->uuid();

    Sanctum::actingAs($userA);
    $this->postJson('/api/categories', [
        'name' => 'Category A',
    ], ['X-Idempotency-Key' => $key])->assertCreated();

    Sanctum::actingAs($userB);
    $this->postJson('/api/categories', [
        'name' => 'Category B',
    ], ['X-Idempotency-Key' => $key])->assertCreated();

    $this->assertDatabaseCount('categories', 2);
    $this->assertDatabaseCount('idempotency_keys', 2);
});

it('stores idempotency key even for failed requests', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $key = fake()->uuid();

    // Send invalid data to trigger validation error
    $response = $this->postJson('/api/categories', [], [
        'X-Idempotency-Key' => $key,
    ]);

    $response->assertUnprocessable();

    $this->assertDatabaseHas('idempotency_keys', [
        'key' => $key,
        'status_code' => 422,
    ]);
});

it('replays the stored error response on retry with same key', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $key = fake()->uuid();

    // First: validation error
    $this->postJson('/api/categories', [], [
        'X-Idempotency-Key' => $key,
    ])->assertUnprocessable();

    // Second: same key replays the 422
    $response = $this->postJson('/api/categories', [
        'name' => 'Valid Name',
    ], ['X-Idempotency-Key' => $key]);

    $response->assertUnprocessable();
    $response->assertHeader('X-Idempotent-Replayed', 'true');
});

it('cleans up old idempotency keys', function () {
    $user = User::factory()->vendor()->create();

    // Create an old key
    IdempotencyKey::query()->create([
        'key' => fake()->uuid(),
        'user_id' => $user->id,
        'endpoint' => 'api/categories',
        'http_method' => 'POST',
        'status_code' => 201,
        'response' => '{}',
        'created_at' => now()->subHours(25),
    ]);

    // Create a recent key
    IdempotencyKey::query()->create([
        'key' => fake()->uuid(),
        'user_id' => $user->id,
        'endpoint' => 'api/categories',
        'http_method' => 'POST',
        'status_code' => 201,
        'response' => '{}',
        'created_at' => now(),
    ]);

    $this->artisan('idempotency:clean')
        ->assertSuccessful();

    $this->assertDatabaseCount('idempotency_keys', 1);
});

it('does not process idempotency for unauthenticated requests', function () {
    $key = fake()->uuid();

    $response = $this->postJson('/api/categories', [
        'name' => 'Test',
    ], ['X-Idempotency-Key' => $key]);

    $response->assertUnauthorized();
    $this->assertDatabaseCount('idempotency_keys', 0);
});
