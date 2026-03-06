<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates a credit order with new customer', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 1000,
        'cost' => 600,
        'stock' => 50,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'ordered_at' => '2026-03-04',
        'status' => 'completed',
        'payment_method' => 'credit',
        'credit_customer' => [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'contact_number' => '09171234567',
            'address' => '123 Main St, Butuan City',
        ],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.total', 3000);

    // Customer was created with credit balance, phone, and address
    $this->assertDatabaseHas('customers', [
        'user_id' => $user->id,
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'name' => 'Juan Santos Dela Cruz',
        'phone' => '09171234567',
        'address' => '123 Main St, Butuan City',
        'credit_balance' => 3000,
    ]);

    // Payment was created with credit method and pending status
    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'method' => 'credit',
        'status' => 'pending',
        'amount' => 3000,
    ]);

    // Ledger entry was created for credit
    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'type' => 'credit',
        'category' => 'financial',
        'amount' => 3000,
    ]);
});

it('creates a credit order with existing customer and updates credit balance', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create([
        'name' => 'Old Name',
        'first_name' => 'Old',
        'last_name' => 'Name',
        'credit_balance' => 5000,
        'orders_count' => 2,
        'total_spent' => 10000,
    ]);
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 2000,
        'cost' => 1000,
        'stock' => 50,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'ordered_at' => '2026-03-04',
        'status' => 'completed',
        'payment_method' => 'credit',
        'credit_customer' => [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'contact_number' => '09281234567',
            'address' => '456 Rizal Ave, Davao City',
        ],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ]);

    $response->assertCreated();

    $customer->refresh();
    expect($customer->credit_balance)->toBe(7000);
    expect($customer->first_name)->toBe('Maria');
    expect($customer->last_name)->toBe('Santos');
    expect($customer->name)->toBe('Maria Santos');
    expect($customer->phone)->toBe('09281234567');
    expect($customer->address)->toBe('456 Rizal Ave, Davao City');
    expect($customer->orders_count)->toBe(3);
    expect($customer->total_spent)->toBe(12000);
});

it('requires customer name fields for credit payment', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 1000,
        'cost' => 600,
        'stock' => 50,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'ordered_at' => '2026-03-04',
        'status' => 'completed',
        'payment_method' => 'credit',
        'credit_customer' => [],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['credit_customer.first_name', 'credit_customer.last_name']);
});

it('records a credit repayment and reduces balance', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create([
        'credit_balance' => 10000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/payments/credit', [
        'customer_id' => $customer->id,
        'amount' => 3000,
        'paid_at' => '2026-03-04 14:00',
        'method' => 'cash',
        'note' => 'Partial repayment',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.amount', 3000);
    $response->assertJsonPath('data.method', 'cash');
    $response->assertJsonPath('data.status', 'completed');

    $customer->refresh();
    expect($customer->credit_balance)->toBe(7000);

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'type' => 'credit_payment',
        'category' => 'financial',
        'amount' => 3000,
    ]);
});

it('prevents repayment exceeding credit balance', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create([
        'credit_balance' => 5000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/payments/credit', [
        'customer_id' => $customer->id,
        'amount' => 6000,
        'paid_at' => '2026-03-04 14:00',
        'method' => 'cash',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['amount']);
});

it('handles full repayment correctly', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create([
        'credit_balance' => 5000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/payments/credit', [
        'customer_id' => $customer->id,
        'amount' => 5000,
        'paid_at' => '2026-03-04 14:00',
        'method' => 'card',
    ]);

    $response->assertCreated();

    $customer->refresh();
    expect($customer->credit_balance)->toBe(0);
});

it('returns customer credit history', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();

    // Create credit and credit_payment ledger entries
    \App\Models\LedgerEntry::factory()->for($user)->credit()->create([
        'customer_id' => $customer->id,
        'amount' => 5000,
        'product_id' => null,
    ]);
    \App\Models\LedgerEntry::factory()->for($user)->creditPayment()->create([
        'customer_id' => $customer->id,
        'amount' => 2000,
        'product_id' => null,
    ]);
    // Non-credit entry should not appear
    \App\Models\LedgerEntry::factory()->for($user)->sale()->create([
        'customer_id' => $customer->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/customers/{$customer->id}/credits");

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
});

it('creates non-credit orders normally without credit fields', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'price' => 500,
        'cost' => 300,
        'stock' => 50,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'ordered_at' => '2026-03-04',
        'status' => 'completed',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.total', 1000);

    // No credit payment or customer created
    $this->assertDatabaseMissing('payments', [
        'user_id' => $user->id,
        'method' => 'credit',
    ]);
    $this->assertDatabaseMissing('ledger_entries', [
        'user_id' => $user->id,
        'type' => 'credit',
    ]);
});

it('includes credit fields in payment summary', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    Payment::factory()->for($user)->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $customer = Customer::factory()->for($user)->create(['credit_balance' => 8000]);
    Payment::factory()->for($user)->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'amount' => 5000,
        'method' => 'credit',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payments/summary');

    $response->assertSuccessful();
    $response->assertJsonPath('data.total_revenue', 1000);
    $response->assertJsonPath('data.credit_payments', 5000);
    $response->assertJsonPath('data.outstanding_credit', 8000);
});

it('includes credit_balance in customer resource', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'credit_balance' => 7500,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/customers/{$customer->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.first_name', 'Juan');
    $response->assertJsonPath('data.middle_name', 'Santos');
    $response->assertJsonPath('data.last_name', 'Dela Cruz');
    $response->assertJsonPath('data.credit_balance', 7500);
});
