<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists customers for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $customer = Customer::factory()->for($user)->create();
    Customer::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'id' => $customer->id,
        'name' => $customer->name,
    ]);
});

it('filters customers by search term', function () {
    $user = User::factory()->create();

    $customer1 = Customer::factory()->for($user)->create(['name' => 'John Doe']);
    Customer::factory()->for($user)->create(['name' => 'Jane Smith']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers?search=John');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $customer1->id]);
});

it('filters customers by email search', function () {
    $user = User::factory()->create();

    $customer1 = Customer::factory()->for($user)->create(['email' => 'john@test.com']);
    Customer::factory()->for($user)->create(['email' => 'jane@example.com']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers?search=test.com');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $customer1->id]);
});

it('filters customers by status', function () {
    $user = User::factory()->create();

    $customer1 = Customer::factory()->for($user)->create(['status' => 'active']);
    Customer::factory()->for($user)->create(['status' => 'inactive']);
    Customer::factory()->for($user)->create(['status' => 'vip']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers?status=active');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $customer1->id]);
});

it('returns customer summary', function () {
    $user = User::factory()->create();

    Customer::factory()->for($user)->count(3)->create(['status' => 'active']);
    Customer::factory()->for($user)->count(2)->create(['status' => 'vip']);
    Customer::factory()->for($user)->count(1)->create(['status' => 'inactive']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers/summary');

    $response->assertSuccessful();
    $response->assertJsonPath('data.total_customers', 6);
    $response->assertJsonPath('data.active', 3);
    $response->assertJsonPath('data.vip', 2);
    $response->assertJsonPath('data.inactive', 1);
});

it('creates a customer', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', [
        'name' => 'New Customer',
        'email' => 'customer@test.com',
        'phone' => '+63 912 345 6789',
        'status' => 'active',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'New Customer',
        'email' => 'customer@test.com',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('customers', [
        'user_id' => $user->id,
        'name' => 'New Customer',
        'email' => 'customer@test.com',
    ]);
});

it('validates required fields when creating a customer', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'status']);
});

it('prevents duplicate email for the same user', function () {
    $user = User::factory()->create();

    Customer::factory()->for($user)->create(['email' => 'duplicate@test.com']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', [
        'name' => 'Another Customer',
        'email' => 'duplicate@test.com',
        'status' => 'active',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

it('shows a customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers/'.$customer->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $customer->id,
        'name' => $customer->name,
        'email' => $customer->email,
    ]);
});

it('returns 404 for non-existent customer', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers/99999');

    $response->assertNotFound();
});

it('cannot view another users customer', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/customers/'.$customer->id);

    $response->assertNotFound();
});

it('updates a customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/customers/'.$customer->id, [
        'name' => 'Updated Name',
        'status' => 'vip',
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'Updated Name',
        'status' => 'vip',
    ]);

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Updated Name',
        'status' => 'vip',
    ]);
});

it('cannot update another users customer', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/customers/'.$customer->id, [
        'name' => 'Hacked Name',
    ]);

    $response->assertNotFound();
});

it('deletes a customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/customers/'.$customer->id);

    $response->assertNoContent();

    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
});

it('cannot delete another users customer', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/customers/'.$customer->id);

    $response->assertNotFound();

    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
});

it('requires authentication to access customers', function () {
    $response = $this->getJson('/api/customers');

    $response->assertUnauthorized();
});
