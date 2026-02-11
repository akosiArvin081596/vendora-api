<?php

use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists ledger entries for the authenticated user', function () {
    $user = User::factory()->vendor()->create();
    $otherUser = User::factory()->vendor()->create();

    LedgerEntry::factory()->for($user)->sale()->create();
    LedgerEntry::factory()->for($user)->stockIn()->create();
    LedgerEntry::factory()->for($otherUser)->sale()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/ledger');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
});

it('filters ledger entries by type', function () {
    $user = User::factory()->vendor()->create();

    LedgerEntry::factory()->for($user)->sale()->create();
    LedgerEntry::factory()->for($user)->stockIn()->create();
    LedgerEntry::factory()->for($user)->expense()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/ledger?type=sale');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('filters ledger entries by category', function () {
    $user = User::factory()->vendor()->create();

    LedgerEntry::factory()->for($user)->sale()->create();
    LedgerEntry::factory()->for($user)->stockIn()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/ledger?category=financial');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('searches ledger entries by description', function () {
    $user = User::factory()->vendor()->create();

    LedgerEntry::factory()->for($user)->sale()->create(['description' => 'Sale of Rice']);
    LedgerEntry::factory()->for($user)->expense()->create(['description' => 'Electricity bill']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/ledger?search=Rice');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('returns ledger summary', function () {
    $user = User::factory()->vendor()->create();

    LedgerEntry::factory()->for($user)->create([
        'type' => 'stock_in',
        'category' => 'inventory',
        'quantity' => 50,
        'amount' => null,
    ]);
    LedgerEntry::factory()->for($user)->create([
        'type' => 'stock_out',
        'category' => 'inventory',
        'quantity' => -20,
        'amount' => null,
    ]);
    LedgerEntry::factory()->for($user)->create([
        'type' => 'sale',
        'category' => 'financial',
        'quantity' => null,
        'amount' => 10000,
    ]);
    LedgerEntry::factory()->for($user)->create([
        'type' => 'expense',
        'category' => 'financial',
        'quantity' => null,
        'amount' => -3000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/ledger/summary');

    $response->assertSuccessful();
    $response->assertJson([
        'data' => [
            'total_stock_in' => 50,
            'total_stock_out' => 20,
            'total_revenue' => 10000,
            'total_expenses' => 3000,
            'net_profit' => 7000,
        ],
    ]);
});

it('creates a manual stock_in ledger entry', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 10]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/ledger', [
        'type' => 'stock_in',
        'product_id' => $product->id,
        'quantity' => 5,
        'description' => 'Restocked from supplier',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'type' => 'stock_in',
        'quantity' => 5,
    ]);

    // Verify product stock was updated
    expect($product->fresh()->stock)->toBe(15);
});

it('creates a manual expense ledger entry', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/ledger', [
        'type' => 'expense',
        'amount' => 5000,
        'description' => 'Store rent payment',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'type' => 'expense',
        'amount' => -5000,
    ]);
});

it('validates required fields when creating ledger entry', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/ledger', []);

    $response->assertUnprocessable();
});

it('rejects invalid entry type', function () {
    $user = User::factory()->vendor()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/ledger', [
        'type' => 'sale',
        'description' => 'Should fail',
    ]);

    $response->assertUnprocessable();
});

it('requires authentication for ledger endpoints', function () {
    $this->getJson('/api/ledger')->assertUnauthorized();
    $this->getJson('/api/ledger/summary')->assertUnauthorized();
    $this->postJson('/api/ledger', [])->assertUnauthorized();
});

it('creates ledger entries when an order is placed', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 100,
        'price' => 500,
        'cost' => 300,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'ordered_at' => now()->toDateString(),
        'status' => 'completed',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ]);

    // Should have a sale entry, a COGS expense entry, and a stock_out entry
    $saleEntries = LedgerEntry::query()->where('user_id', $user->id)->where('type', 'sale')->count();
    $expenseEntries = LedgerEntry::query()->where('user_id', $user->id)->where('type', 'expense')->count();
    $stockOutEntries = LedgerEntry::query()->where('user_id', $user->id)->where('type', 'stock_out')->count();

    expect($saleEntries)->toBe(1);
    expect($expenseEntries)->toBe(1);
    expect($stockOutEntries)->toBe(1);
});

it('creates ledger entry when inventory is adjusted', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create(['stock' => 10]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 5,
        'note' => 'Restock',
    ]);

    $entry = LedgerEntry::query()->where('user_id', $user->id)->where('type', 'stock_in')->first();

    expect($entry)->not->toBeNull();
    expect($entry->quantity)->toBe(5);
    expect($entry->balance_qty)->toBe(15);
});

it('creates ledger entry when a new product is created with initial stock', function () {
    Storage::fake('public');

    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'Test Product',
        'sku' => 'TEST-001',
        'category_id' => $category->id,
        'price' => 1000,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 50,
        'is_active' => true,
        'is_ecommerce' => false,
        'image' => UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertCreated();

    $product = Product::query()->where('sku', 'TEST-001')->first();
    $entry = LedgerEntry::query()
        ->where('user_id', $user->id)
        ->where('product_id', $product->id)
        ->where('type', 'stock_in')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->quantity)->toBe(50);
    expect($entry->balance_qty)->toBe(50);
    expect($entry->description)->toContain('Initial stock');
});

it('does not create ledger entry when product is created with zero stock', function () {
    Storage::fake('public');

    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/products', [
        'name' => 'Zero Stock Product',
        'sku' => 'ZERO-001',
        'category_id' => $category->id,
        'price' => 500,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 0,
        'is_active' => true,
        'is_ecommerce' => false,
        'image' => UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg'),
    ]);

    $product = Product::query()->where('sku', 'ZERO-001')->first();
    $entry = LedgerEntry::query()
        ->where('user_id', $user->id)
        ->where('product_id', $product->id)
        ->first();

    expect($entry)->toBeNull();
});

it('creates a COGS expense ledger entry when order is placed', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'price' => 1000,
        'cost' => 600,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'ordered_at' => now()->toDateString(),
        'status' => 'completed',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5],
        ],
    ])->assertCreated();

    $cogsEntry = LedgerEntry::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->where('description', 'like', 'COGS%')
        ->first();

    expect($cogsEntry)->not->toBeNull();
    expect($cogsEntry->amount)->toBe(-3000); // 5 * 600 = 3000, stored as negative
    expect($cogsEntry->category)->toBe('financial');
});

it('calculates net profit correctly with COGS', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 100,
        'price' => 1000,
        'cost' => 700,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'ordered_at' => now()->toDateString(),
        'status' => 'completed',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 10],
        ],
    ])->assertCreated();

    // Revenue = 10 * 1000 = 10000, COGS = 10 * 700 = 7000, Net Profit = 3000
    $response = $this->getJson('/api/ledger/summary');

    $response->assertSuccessful();
    $response->assertJson([
        'data' => [
            'total_revenue' => 10000,
            'total_expenses' => 7000,
            'net_profit' => 3000,
        ],
    ]);
});

it('shows zero profit margin when product has no cost set', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 20,
        'price' => 500,
        'cost' => null,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'ordered_at' => now()->toDateString(),
        'status' => 'completed',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 4],
        ],
    ])->assertCreated();

    // Revenue = 4 * 500 = 2000, COGS = 4 * 500 = 2000 (falls back to price), Net Profit = 0
    $response = $this->getJson('/api/ledger/summary');

    $response->assertSuccessful();
    $response->assertJson([
        'data' => [
            'total_revenue' => 2000,
            'total_expenses' => 2000,
            'net_profit' => 0,
        ],
    ]);
});
