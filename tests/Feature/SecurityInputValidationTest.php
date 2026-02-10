<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Input Validation & Injection Prevention Tests
|--------------------------------------------------------------------------
*/

describe('SQL Injection Prevention', function () {
    it('prevents SQL injection in product search', function () {
        $user = User::factory()->vendor()->create();
        Product::factory()->for($user)->create(['name' => 'Normal Product']);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/products?search=' OR '1'='1");

        $response->assertSuccessful();
        expect($response->json('data'))->toBeEmpty();
    })->skip('Public product endpoints temporarily disabled');

    it('prevents SQL injection in customer search', function () {
        $user = User::factory()->vendor()->create();
        Customer::factory()->for($user)->create(['name' => 'Normal Customer']);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/customers?search='; DROP TABLE customers; --");

        $response->assertSuccessful();
        // Table should still exist (no error)
        expect(Customer::count())->toBe(1);
    });

    it('prevents SQL injection in order number search', function () {
        $user = User::factory()->vendor()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders?search=1; DELETE FROM orders WHERE 1=1; --');

        $response->assertSuccessful();
    });

    it('prevents SQL injection in sort parameter', function () {
        $user = User::factory()->vendor()->create();
        Product::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Malicious sort parameter should be ignored and use default
        $response = $this->getJson('/api/products?sort=name; DROP TABLE products; --');

        $response->assertSuccessful();
        expect(Product::count())->toBe(1);
    })->skip('Public product endpoints temporarily disabled');
});

describe('XSS Prevention', function () {
    it('stores XSS payload as plain text in product name', function () {
        $user = User::factory()->vendor()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->postJson('/api/products', [
            'name' => $xssPayload,
            'sku' => 'XSS-TEST',
            'category_id' => $category->id,
            'price' => 100,
            'currency' => 'PHP',
            'unit' => 'pc',
            'stock' => 10,
            'is_active' => true,
            'is_ecommerce' => true,
        ]);

        $response->assertCreated();
        // Script should be stored as-is (API returns JSON, no HTML rendering)
        expect($response->json('data.name'))->toBe($xssPayload);
    });

    it('stores XSS payload as plain text in customer name', function () {
        $user = User::factory()->vendor()->create();

        Sanctum::actingAs($user);

        $xssPayload = '<img src=x onerror=alert("XSS")>';

        $response = $this->postJson('/api/customers', [
            'name' => $xssPayload,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $response->assertCreated();
        expect($response->json('data.name'))->toBe($xssPayload);
    });
});

describe('Integer Overflow Prevention', function () {
    it('rejects extremely large price values', function () {
        $user = User::factory()->vendor()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'name' => 'Overpriced Item',
            'sku' => 'OVERFLOW-TEST',
            'category_id' => $category->id,
            'price' => PHP_INT_MAX + 1,
            'stock' => 10,
        ]);

        $response->assertUnprocessable();
    });

    it('rejects negative stock values', function () {
        $user = User::factory()->vendor()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'name' => 'Negative Stock',
            'sku' => 'NEG-STOCK',
            'category_id' => $category->id,
            'price' => 100,
            'stock' => -100,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['stock']);
    });

    it('rejects negative payment amount', function () {
        $user = User::factory()->vendor()->create();
        $order = Order::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => -1000,
            'method' => 'cash',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount']);
    });

    it('rejects zero payment amount', function () {
        $user = User::factory()->vendor()->create();
        $order = Order::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 0,
            'method' => 'cash',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount']);
    });
});

describe('Business Logic Validation', function () {
    it('prevents ordering more than available stock', function () {
        $user = User::factory()->vendor()->create();
        $customer = Customer::factory()->for($user)->create();
        $product = Product::factory()->for($user)->create(['stock' => 5]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100],
            ],
        ]);

        $response->assertUnprocessable();
    });

    it('prevents inventory adjustment resulting in negative stock', function () {
        $user = User::factory()->vendor()->create();
        $product = Product::factory()->for($user)->create(['stock' => 10]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/inventory/adjustments', [
            'product_id' => $product->id,
            'type' => 'remove',
            'quantity' => 50,
        ]);

        $response->assertUnprocessable();
        expect($product->fresh()->stock)->toBe(10);
    });

    it('rejects invalid order status', function () {
        $user = User::factory()->vendor()->create();
        $order = Order::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'hacked_status',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    });

    it('rejects invalid payment method', function () {
        $user = User::factory()->vendor()->create();
        $order = Order::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 1000,
            'method' => 'bitcoin_hack',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['method']);
    });
});

describe('Type Coercion Protection', function () {
    it('rejects array where string expected', function () {
        $user = User::factory()->vendor()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'name' => ['array', 'instead', 'of', 'string'],
            'sku' => 'TYPE-TEST',
            'category_id' => $category->id,
            'price' => 100,
            'stock' => 10,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    });

    it('rejects string where integer expected', function () {
        $user = User::factory()->vendor()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TYPE-TEST-2',
            'category_id' => $category->id,
            'price' => 'not-a-number',
            'stock' => 10,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['price']);
    });

    it('rejects object where integer expected for category_id', function () {
        $user = User::factory()->vendor()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TYPE-TEST-3',
            'category_id' => ['id' => 1],
            'price' => 100,
            'stock' => 10,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['category_id']);
    });
});

describe('Length Limits', function () {
    it('rejects overly long product name', function () {
        $user = User::factory()->vendor()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'name' => str_repeat('A', 500),
            'sku' => 'LONG-NAME',
            'category_id' => $category->id,
            'price' => 100,
            'stock' => 10,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    });

    it('rejects overly long email', function () {
        $response = $this->postJson('/api/auth/register', [
            'business_name' => 'Test Business',
            'email' => str_repeat('a', 300).'@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'subscription_plan' => 'basic',
            'user_type' => 'vendor',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    });
});

describe('Email Validation', function () {
    it('rejects invalid email format', function () {
        $response = $this->postJson('/api/auth/register', [
            'business_name' => 'Test Business',
            'email' => 'not-an-email',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'subscription_plan' => 'basic',
            'user_type' => 'vendor',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    });

    it('rejects email with special characters injection', function () {
        $response = $this->postJson('/api/auth/register', [
            'business_name' => 'Test Business',
            'email' => 'test@example.com\nBcc: hacker@evil.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'subscription_plan' => 'basic',
            'user_type' => 'vendor',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    });
});
