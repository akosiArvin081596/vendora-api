<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Authorization Boundary Tests
|--------------------------------------------------------------------------
| These tests verify that users cannot access or modify resources
| belonging to other users (IDOR prevention).
*/

describe('Product Authorization', function () {
    it('prevents user from viewing another user\'s product', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherProduct = Product::factory()->for($otherUser)->create([
            'is_active' => true,
            'is_ecommerce' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/products/{$otherProduct->id}");

        $response->assertNotFound();
    })->skip('Public product endpoints temporarily disabled');

    it('prevents user from updating another user\'s product', function () {
        $user = User::factory()->vendor()->create();
        $otherUser = User::factory()->vendor()->create();
        $otherProduct = Product::factory()->for($otherUser)->create([
            'is_active' => true,
            'is_ecommerce' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/products/{$otherProduct->id}", [
            'name' => 'Hacked Product Name',
            'sku' => 'HACKED-SKU',
            'price' => 99999,
            'stock' => 1000,
        ]);

        $response->assertNotFound();
        expect($otherProduct->fresh()->name)->not->toBe('Hacked Product Name');
    });

    it('prevents user from deleting another user\'s product', function () {
        $user = User::factory()->vendor()->create();
        $otherUser = User::factory()->vendor()->create();
        $otherProduct = Product::factory()->for($otherUser)->create([
            'is_active' => true,
            'is_ecommerce' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/products/{$otherProduct->id}");

        $response->assertNotFound();
        expect($otherProduct->fresh())->not->toBeNull();
    });

    it('does not list products from other users', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Product::factory()->for($user)->create(['name' => 'My Product', 'is_active' => true, 'is_ecommerce' => true]);
        Product::factory()->for($otherUser)->create(['name' => 'Other Product', 'is_active' => true, 'is_ecommerce' => false]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/products');

        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'My Product']);
        $response->assertJsonMissing(['name' => 'Other Product']);
    })->skip('Public product endpoints temporarily disabled');
});

describe('Customer Authorization', function () {
    it('prevents user from viewing another user\'s customer', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCustomer = Customer::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/customers/{$otherCustomer->id}");

        $response->assertNotFound();
    });

    it('prevents user from updating another user\'s customer', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCustomer = Customer::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/customers/{$otherCustomer->id}", [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
        ]);

        $response->assertNotFound();
        expect($otherCustomer->fresh()->name)->not->toBe('Hacked Name');
    });

    it('prevents user from deleting another user\'s customer', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCustomer = Customer::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/customers/{$otherCustomer->id}");

        $response->assertNotFound();
        expect($otherCustomer->fresh())->not->toBeNull();
    });

    it('does not list customers from other users', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Customer::factory()->for($user)->create(['name' => 'My Customer']);
        Customer::factory()->for($otherUser)->create(['name' => 'Other Customer']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/customers');

        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'My Customer']);
        $response->assertJsonMissing(['name' => 'Other Customer']);
    });
});

describe('Order Authorization', function () {
    it('prevents user from viewing another user\'s order', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/orders/{$otherOrder->id}");

        $response->assertNotFound();
    });

    it('prevents user from updating another user\'s order', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/orders/{$otherOrder->id}", [
            'status' => 'completed',
        ]);

        $response->assertNotFound();
    });

    it('prevents user from deleting another user\'s order', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/orders/{$otherOrder->id}");

        $response->assertNotFound();
        expect($otherOrder->fresh())->not->toBeNull();
    });

    it('prevents user from creating order with another user\'s customer', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCustomer = Customer::factory()->for($otherUser)->create();
        $product = Product::factory()->for($user)->create(['stock' => 100]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $otherCustomer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['customer_id']);
    });

    it('prevents user from creating order with another user\'s product', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $otherProduct = Product::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $otherProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items.0.product_id']);
    });
});

describe('Payment Authorization', function () {
    it('prevents user from viewing another user\'s payment', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherPayment = Payment::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/payments/{$otherPayment->id}");

        $response->assertNotFound();
    });

    it('prevents user from updating another user\'s payment', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherPayment = Payment::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/payments/{$otherPayment->id}", [
            'status' => 'completed',
        ]);

        $response->assertNotFound();
    });

    it('prevents user from deleting another user\'s payment', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherPayment = Payment::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/payments/{$otherPayment->id}");

        $response->assertNotFound();
        expect($otherPayment->fresh())->not->toBeNull();
    });

    it('prevents user from creating payment for another user\'s order', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_id' => $otherOrder->id,
            'amount' => 1000,
            'method' => 'cash',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order_id']);
    });
});

describe('Inventory Authorization', function () {
    it('prevents user from adjusting another user\'s product inventory', function () {
        $user = User::factory()->vendor()->create();
        $otherUser = User::factory()->vendor()->create();
        $otherProduct = Product::factory()->for($otherUser)->create(['stock' => 50]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/inventory/adjustments', [
            'product_id' => $otherProduct->id,
            'type' => 'add',
            'quantity' => 100,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['product_id']);
        expect($otherProduct->fresh()->stock)->toBe(50);
    });

    it('does not include other user\'s products in inventory list', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Product::factory()->for($user)->create(['name' => 'My Inventory']);
        Product::factory()->for($otherUser)->create(['name' => 'Other Inventory']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/inventory');

        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'My Inventory']);
        $response->assertJsonMissing(['name' => 'Other Inventory']);
    });
});

describe('ID Enumeration Protection', function () {
    it('returns 404 for non-existent product ID (not 403)', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/products/999999');

        // Should return 404, not 403, to avoid revealing ID existence
        $response->assertNotFound();
    })->skip('Public product endpoints temporarily disabled');

    it('returns 404 for non-existent order ID (not 403)', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders/999999');

        $response->assertNotFound();
    });
});
