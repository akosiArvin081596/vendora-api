<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Public Product Endpoints', function () {
    it('lists all active products without authentication', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Product::factory()->for($user)->for($category)->create(['is_active' => true, 'is_ecommerce' => true]);
        Product::factory()->for($user)->for($category)->create(['is_active' => true, 'is_ecommerce' => true]);
        Product::factory()->for($user)->for($category)->create(['is_active' => true, 'is_ecommerce' => false]);
        Product::factory()->for($user)->for($category)->create(['is_active' => false]);

        $response = $this->getJson('/api/products');

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    })->skip('Public product endpoints temporarily disabled');

    it('filters products by user_id', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $category = Category::factory()->create();

        $product1 = Product::factory()->for($user1)->for($category)->create(['is_active' => true, 'is_ecommerce' => true]);
        Product::factory()->for($user2)->for($category)->create(['is_active' => true, 'is_ecommerce' => true]);

        $response = $this->getJson('/api/products?user_id='.$user1->id);

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $product1->id]);
    })->skip('Public product endpoints temporarily disabled');

    it('filters products by store_id', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $store = Store::factory()->for($user)->create();

        $productInStore = Product::factory()->for($user)->for($category)->create(['is_active' => true, 'is_ecommerce' => false]);
        $productNotInStore = Product::factory()->for($user)->for($category)->create(['is_active' => true, 'is_ecommerce' => true]);

        StoreProduct::factory()->for($store)->for($productInStore)->create(['is_available' => true]);

        $response = $this->getJson('/api/products?store_id='.$store->id);

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $productInStore->id]);
    })->skip('Public product endpoints temporarily disabled');

    it('shows product details without authentication', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create(['is_active' => true, 'is_ecommerce' => true]);

        $response = $this->getJson('/api/products/'.$product->id);

        $response->assertSuccessful();
        $response->assertJsonFragment([
            'id' => $product->id,
            'name' => $product->name,
        ]);
    })->skip('Public product endpoints temporarily disabled');

    it('returns 404 for inactive product on show', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create(['is_active' => false, 'is_ecommerce' => true]);

        $response = $this->getJson('/api/products/'.$product->id);

        $response->assertNotFound();
    })->skip('Public product endpoints temporarily disabled');

    it('finds product by SKU without authentication', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create([
            'is_active' => true,
            'is_ecommerce' => true,
            'sku' => 'TEST-SKU-001',
        ]);

        $response = $this->getJson('/api/products/sku/TEST-SKU-001');

        $response->assertSuccessful();
        $response->assertJsonFragment([
            'id' => $product->id,
            'sku' => 'TEST-SKU-001',
        ]);
    })->skip('Public product endpoints temporarily disabled');

    it('finds product by barcode without authentication', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create([
            'is_active' => true,
            'is_ecommerce' => true,
            'barcode' => '1234567890123',
        ]);

        $response = $this->getJson('/api/products/barcode/1234567890123');

        $response->assertSuccessful();
        $response->assertJsonFragment([
            'id' => $product->id,
            'barcode' => '1234567890123',
        ]);
    })->skip('Public product endpoints temporarily disabled');

    it('returns 404 for inactive product on SKU lookup', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Product::factory()->for($user)->for($category)->create([
            'is_active' => false,
            'is_ecommerce' => true,
            'sku' => 'INACTIVE-SKU',
        ]);

        $response = $this->getJson('/api/products/sku/INACTIVE-SKU');

        $response->assertNotFound();
    })->skip('Public product endpoints temporarily disabled');

    it('returns 404 for non-ecommerce product on show', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create([
            'is_active' => true,
            'is_ecommerce' => false,
        ]);

        $response = $this->getJson('/api/products/'.$product->id);

        $response->assertNotFound();
    })->skip('Public product endpoints temporarily disabled');

    it('returns 404 for non-ecommerce product on SKU lookup', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Product::factory()->for($user)->for($category)->create([
            'is_active' => true,
            'is_ecommerce' => false,
            'sku' => 'POS-ONLY-SKU',
        ]);

        $response = $this->getJson('/api/products/sku/POS-ONLY-SKU');

        $response->assertNotFound();
    })->skip('Public product endpoints temporarily disabled');
});

describe('Public Category Endpoints', function () {
    it('lists categories without authentication', function () {
        Category::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('shows category details without authentication', function () {
        $category = Category::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/categories/'.$category->id);

        $response->assertSuccessful();
        $response->assertJsonFragment([
            'id' => $category->id,
            'name' => $category->name,
        ]);
    });
});

describe('Protected Product Endpoints', function () {
    it('requires authentication to create a product', function () {
        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TST-001',
            'price' => 1500,
        ]);

        $response->assertUnauthorized();
    });

    it('requires authentication to update a product', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create();

        $response = $this->patchJson('/api/products/'.$product->id, [
            'name' => 'Updated Name',
        ]);

        $response->assertUnauthorized();
    });

    it('requires authentication to delete a product', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create();

        $response = $this->deleteJson('/api/products/'.$product->id);

        $response->assertUnauthorized();
    });

    it('requires authentication to update stock', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create();

        $response = $this->patchJson('/api/products/'.$product->id.'/stock', [
            'stock' => 100,
        ]);

        $response->assertUnauthorized();
    });

    it('requires authentication for bulk stock decrement', function () {
        $response = $this->postJson('/api/products/bulk-stock-decrement', [
            'items' => [
                ['productId' => 1, 'quantity' => 2],
            ],
        ]);

        $response->assertUnauthorized();
    });
});

describe('Protected Category Endpoints', function () {
    it('requires authentication to create a category', function () {
        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
        ]);

        $response->assertUnauthorized();
    });

    it('requires authentication to update a category', function () {
        $category = Category::factory()->create();

        $response = $this->patchJson('/api/categories/'.$category->id, [
            'name' => 'Updated Name',
        ]);

        $response->assertUnauthorized();
    });

    it('requires authentication to delete a category', function () {
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/categories/'.$category->id);

        $response->assertUnauthorized();
    });
});

describe('User Store Information', function () {
    it('includes stores in user profile', function () {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create(['name' => 'My Store']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertSuccessful();
        $response->assertJsonFragment([
            'id' => $user->id,
        ]);
        $response->assertJsonPath('stores.0.name', 'My Store');
    });

    it('includes assigned stores for staff members', function () {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $store = Store::factory()->for($owner)->create(['name' => 'Assigned Store']);

        $store->staff()->attach($staff->id, [
            'role' => 'cashier',
            'permissions' => json_encode([]),
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/user');

        $response->assertSuccessful();
        $response->assertJsonPath('assigned_stores.0.name', 'Assigned Store');
        $response->assertJsonPath('assigned_stores.0.role', 'cashier');
    });

    it('includes stores in login response', function () {
        $user = User::factory()->create([
            'email' => 'vendor@test.com',
            'password' => bcrypt('password123'),
            'user_type' => 'vendor',
        ]);
        $store = Store::factory()->for($user)->create(['name' => 'Vendor Store']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'vendor@test.com',
            'password' => 'password123',
            'user_type' => 'vendor',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('user.stores.0.name', 'Vendor Store');
    });
});
