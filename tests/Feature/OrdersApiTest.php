<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists orders for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();

    $order = Order::factory()->for($user)->for($customer)->create();
    Order::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'id' => $order->id,
        'order_number' => $order->order_number,
    ]);
});

it('filters orders by search term', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create(['name' => 'John Doe']);

    $order1 = Order::factory()->for($user)->for($customer)->create(['order_number' => 'ORD-001']);
    Order::factory()->for($user)->create(['order_number' => 'ORD-002']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders?search=John');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $order1->id]);
});

it('filters orders by order number', function () {
    $user = User::factory()->create();

    $order1 = Order::factory()->for($user)->create(['order_number' => 'ORD-ABC']);
    Order::factory()->for($user)->create(['order_number' => 'ORD-XYZ']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders?search=ABC');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $order1->id]);
});

it('filters orders by status', function () {
    $user = User::factory()->create();

    $order1 = Order::factory()->for($user)->create(['status' => 'pending']);
    Order::factory()->for($user)->create(['status' => 'completed']);
    Order::factory()->for($user)->create(['status' => 'processing']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders?status=pending');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['id' => $order1->id]);
});

it('returns order summary', function () {
    $user = User::factory()->create();

    Order::factory()->for($user)->count(3)->create(['status' => 'pending']);
    Order::factory()->for($user)->count(2)->create(['status' => 'processing']);
    Order::factory()->for($user)->count(5)->create(['status' => 'completed']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders/summary');

    $response->assertSuccessful();
    $response->assertJsonPath('data.total_orders', 10);
    $response->assertJsonPath('data.pending', 3);
    $response->assertJsonPath('data.processing', 2);
    $response->assertJsonPath('data.completed', 5);
});

it('creates an order', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 100,
        'stock' => 50,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'ordered_at' => '2026-01-10',
        'status' => 'pending',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.items_count', 2);
    $response->assertJsonPath('data.total', 200);
    $response->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'status' => 'pending',
        'total' => 200,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 48,
    ]);
});

it('creates an order with customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create([
        'orders_count' => 0,
        'total_spent' => 0,
    ]);
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 500,
        'stock' => 20,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'ordered_at' => '2026-01-10',
        'status' => 'pending',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.total', 1500);

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'orders_count' => 1,
        'total_spent' => 1500,
    ]);
});

it('validates required fields when creating an order', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['ordered_at', 'status', 'items']);
});

it('validates items array is not empty', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'ordered_at' => '2026-01-10',
        'status' => 'pending',
        'items' => [],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items']);
});

it('fails when product has insufficient stock', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 100,
        'stock' => 5,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'ordered_at' => '2026-01-10',
        'status' => 'pending',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 10],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items']);
});

it('shows an order', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $order = Order::factory()->for($user)->for($customer)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders/'.$order->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $order->id,
        'order_number' => $order->order_number,
    ]);
});

it('returns 404 for non-existent order', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders/99999');

    $response->assertNotFound();
});

it('cannot view another users order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders/'.$order->id);

    $response->assertNotFound();
});

it('updates an order status', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => 'pending']);

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/orders/'.$order->id, [
        'status' => 'processing',
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['status' => 'processing']);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'processing',
    ]);
});

it('cannot update another users order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($otherUser)->create(['status' => 'pending']);

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/orders/'.$order->id, [
        'status' => 'cancelled',
    ]);

    $response->assertNotFound();
});

it('deletes an order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/orders/'.$order->id);

    $response->assertNoContent();

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});

it('cannot delete another users order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/orders/'.$order->id);

    $response->assertNotFound();

    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

it('requires authentication to access orders', function () {
    $response = $this->getJson('/api/orders');

    $response->assertUnauthorized();
});

it('generates sequential order numbers', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 100,
        'stock' => 100,
    ]);

    Sanctum::actingAs($user);

    $response1 = $this->postJson('/api/orders', [
        'ordered_at' => '2026-01-10',
        'status' => 'pending',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ]);

    $response2 = $this->postJson('/api/orders', [
        'ordered_at' => '2026-01-10',
        'status' => 'pending',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ]);

    $response1->assertCreated();
    $response2->assertCreated();

    $response1->assertJsonPath('data.order_number', 'ORD-001');
    $response2->assertJsonPath('data.order_number', 'ORD-002');
});
