<?php

use App\Exceptions\InsufficientCostLayersException;
use App\Models\Category;
use App\Models\CostLayerConsumption;
use App\Models\InventoryCostLayer;
use App\Models\Product;
use App\Models\User;
use App\Services\FifoCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('consumes a single batch fully', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 10,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $service = app(FifoCostService::class);
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_cost' => 5000,
    ]);

    $result = $service->consumeLayers([
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    expect($result['total_cost'])->toBe(50000);
    expect($result['weighted_average_cost'])->toBe(5000);
    expect(InventoryCostLayer::first()->remaining_quantity)->toBe(0);
});

it('consumes multiple batches in FIFO order', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $service = app(FifoCostService::class);

    // Batch 1: 20 units @ 5000
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'unit_cost' => 5000,
        'acquired_at' => now()->subDay(),
    ]);

    // Batch 2: 30 units @ 6000
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 30,
        'unit_cost' => 6000,
    ]);

    // Sell 25 → COGS = (20 * 5000) + (5 * 6000) = 130000
    $result = $service->consumeLayers([
        'product_id' => $product->id,
        'quantity' => 25,
    ]);

    expect($result['total_cost'])->toBe(130000);
    expect($result['weighted_average_cost'])->toBe(5200); // 130000 / 25

    $layers = InventoryCostLayer::query()->orderBy('acquired_at')->get();
    expect($layers[0]->remaining_quantity)->toBe(0);
    expect($layers[1]->remaining_quantity)->toBe(25);
});

it('partially consumes a layer', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 10,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $service = app(FifoCostService::class);
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_cost' => 5000,
    ]);

    $result = $service->consumeLayers([
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    expect($result['total_cost'])->toBe(15000);
    expect(InventoryCostLayer::first()->remaining_quantity)->toBe(7);
});

it('exhausts a layer exactly', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 15,
        'cost' => 4000,
        'price' => 6000,
    ]);

    $service = app(FifoCostService::class);
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 15,
        'unit_cost' => 4000,
    ]);

    $result = $service->consumeLayers([
        'product_id' => $product->id,
        'quantity' => 15,
    ]);

    expect($result['total_cost'])->toBe(60000);
    expect(InventoryCostLayer::first()->remaining_quantity)->toBe(0);
});

it('auto-creates legacy layer for product with stock but no layers', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $service = app(FifoCostService::class);
    $service->ensureLayersExist($product, $user->id);

    $layer = InventoryCostLayer::first();
    expect($layer)->not->toBeNull();
    expect($layer->quantity)->toBe(50);
    expect($layer->remaining_quantity)->toBe(50);
    expect($layer->unit_cost)->toBe(5000);
    expect($layer->reference)->toBe('MIGRATION');
});

it('throws exception when layers are insufficient', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 5,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $service = app(FifoCostService::class);
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_cost' => 5000,
    ]);

    $service->consumeLayers([
        'product_id' => $product->id,
        'quantity' => 10,
    ]);
})->throws(InsufficientCostLayersException::class);

it('calculates weighted average cost correctly', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $service = app(FifoCostService::class);

    // 20 @ 5000 = 100000, 30 @ 6000 = 180000
    // Total = 280000 / 50 = 5600
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'unit_cost' => 5000,
    ]);
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 30,
        'unit_cost' => 6000,
    ]);

    $avg = $service->getWeightedAverageCost($product->id);
    expect($avg)->toBe(5600);
});

it('creates cost layer on inventory add adjustment', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 10,
        'cost' => 5000,
        'price' => 7500,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 20,
        'unit_cost' => 60.00,
        'note' => 'New batch',
    ])->assertCreated();

    $layer = InventoryCostLayer::query()
        ->where('product_id', $product->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect($layer->quantity)->toBe(20);
    expect($layer->remaining_quantity)->toBe(20);
    expect($layer->unit_cost)->toBe(6000);
});

it('consumes cost layers on inventory remove adjustment', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 20,
        'cost' => 5000,
        'price' => 7500,
    ]);

    // Pre-seed a layer
    InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'remaining_quantity' => 20,
        'unit_cost' => 5000,
        'acquired_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 5,
        'note' => 'Damaged items',
    ])->assertCreated();

    $layer = InventoryCostLayer::first();
    expect($layer->remaining_quantity)->toBe(15);
    expect(CostLayerConsumption::count())->toBe(1);
});

it('creates layer for set adjustment increase', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 10,
        'cost' => 5000,
        'price' => 7500,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'set',
        'quantity' => 25,
        'unit_cost' => 55.00,
        'note' => 'Physical count correction',
    ])->assertCreated();

    // Should create a layer for the 15-unit increase
    $layer = InventoryCostLayer::query()
        ->where('product_id', $product->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect($layer->quantity)->toBe(15);
    expect($layer->unit_cost)->toBe(5500);
});

it('consumes layers for set adjustment decrease', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 25,
        'cost' => 5000,
        'price' => 7500,
    ]);

    InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 25,
        'remaining_quantity' => 25,
        'unit_cost' => 5000,
        'acquired_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'set',
        'quantity' => 10,
        'note' => 'Physical count correction',
    ])->assertCreated();

    $layer = InventoryCostLayer::first();
    expect($layer->remaining_quantity)->toBe(10);
});

it('updates product cost to weighted average on add', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 20,
        'cost' => 5000,
        'price' => 7500,
    ]);

    // Pre-seed existing layer
    InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'remaining_quantity' => 20,
        'unit_cost' => 5000,
        'acquired_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'add',
        'quantity' => 30,
        'unit_cost' => 60.00,
        'note' => 'New batch',
    ])->assertCreated();

    // Weighted average: (20*5000 + 30*6000) / 50 = 280000/50 = 5600
    $product->refresh();
    expect($product->cost)->toBe(5600);
});

it('creates consumption records for bulk stock decrement', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 20,
        'cost' => 5000,
        'price' => 7500,
    ]);

    InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'remaining_quantity' => 20,
        'unit_cost' => 5000,
        'acquired_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/products/bulk-stock-decrement', [
        'items' => [
            ['productId' => $product->id, 'quantity' => 3],
        ],
    ])->assertSuccessful();

    $layer = InventoryCostLayer::first();
    expect($layer->remaining_quantity)->toBe(17);
    expect(CostLayerConsumption::count())->toBe(1);
});

it('creates consumption audit trail with correct data', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 30,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $service = app(FifoCostService::class);

    // Two batches with different costs
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_cost' => 4000,
        'acquired_at' => now()->subDays(2),
    ]);
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'unit_cost' => 6000,
        'acquired_at' => now()->subDay(),
    ]);

    $result = $service->consumeLayers([
        'product_id' => $product->id,
        'quantity' => 15,
    ]);

    expect(count($result['consumptions']))->toBe(2);

    // First consumption: 10 units @ 4000
    expect($result['consumptions'][0]->quantity_consumed)->toBe(10);
    expect($result['consumptions'][0]->unit_cost)->toBe(4000);

    // Second consumption: 5 units @ 6000
    expect($result['consumptions'][1]->quantity_consumed)->toBe(5);
    expect($result['consumptions'][1]->unit_cost)->toBe(6000);

    // Total: (10*4000) + (5*6000) = 70000
    expect($result['total_cost'])->toBe(70000);
});

it('creates initial cost layer when product is created with stock', function () {
    Storage::fake('public');

    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'FIFO Test Product',
        'sku' => 'FIFO-001',
        'category_id' => $category->id,
        'price' => 75.00,
        'cost' => 50.00,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 20,
        'is_active' => true,
        'is_ecommerce' => false,
        'image' => UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertCreated();

    $product = Product::query()->where('sku', 'FIFO-001')->first();
    $layer = InventoryCostLayer::query()
        ->where('product_id', $product->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect($layer->quantity)->toBe(20);
    expect($layer->remaining_quantity)->toBe(20);
    expect($layer->unit_cost)->toBe(5000);
    expect($layer->reference)->toBe('INIT-'.$product->id);
});

it('does not create cost layer when product created with zero stock', function () {
    Storage::fake('public');

    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/products', [
        'name' => 'Zero Stock Product',
        'sku' => 'ZERO-FIFO-001',
        'category_id' => $category->id,
        'price' => 50.00,
        'currency' => 'PHP',
        'unit' => 'pc',
        'stock' => 0,
        'is_active' => true,
        'is_ecommerce' => false,
        'image' => UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg'),
    ])->assertCreated();

    expect(InventoryCostLayer::count())->toBe(0);
});

it('uses FIFO cost for order COGS', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'cost' => 5000,
        'price' => 10000,
    ]);

    $service = app(FifoCostService::class);

    // Batch 1: 20 @ 5000
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'unit_cost' => 5000,
        'acquired_at' => now()->subDay(),
    ]);

    // Batch 2: 30 @ 6000
    $service->createLayer([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 30,
        'unit_cost' => 6000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'ordered_at' => now()->toDateString(),
        'status' => 'completed',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 25],
        ],
    ]);

    $response->assertCreated();

    // FIFO: 20 @ 5000 + 5 @ 6000 = 130000
    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'type' => 'expense',
        'amount' => -130000,
    ]);

    // Layer 1 exhausted, layer 2 has 25 remaining
    $layers = InventoryCostLayer::query()->orderBy('acquired_at')->get();
    expect($layers[0]->remaining_quantity)->toBe(0);
    expect($layers[1]->remaining_quantity)->toBe(25);
});

it('creates cost layer on manual stock_in ledger entry', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 10,
        'cost' => 5000,
        'price' => 7500,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/ledger', [
        'type' => 'stock_in',
        'product_id' => $product->id,
        'quantity' => 5,
        'description' => 'Restocked from supplier',
    ])->assertCreated();

    $layer = InventoryCostLayer::query()
        ->where('product_id', $product->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect($layer->quantity)->toBe(5);
    expect($layer->unit_cost)->toBe(5000);
});

it('removes stock from a specific cost layer via cost_layer_id', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'cost' => 5000,
        'price' => 7500,
    ]);

    // Layer 1: 20 units @ 5000 (oldest)
    $layer1 = InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'remaining_quantity' => 20,
        'unit_cost' => 5000,
        'acquired_at' => now()->subDays(2),
    ]);

    // Layer 2: 30 units @ 6000 (the damaged batch)
    $layer2 = InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 30,
        'remaining_quantity' => 30,
        'unit_cost' => 6000,
        'acquired_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 15,
        'cost_layer_id' => $layer2->id,
        'note' => 'Damaged Feb batch',
    ])->assertCreated();

    // Layer 1 should be untouched
    expect($layer1->fresh()->remaining_quantity)->toBe(20);

    // Layer 2 should have 15 consumed
    expect($layer2->fresh()->remaining_quantity)->toBe(15);

    // Ledger should record cost at layer 2 rate
    $this->assertDatabaseHas('ledger_entries', [
        'product_id' => $product->id,
        'type' => 'stock_out',
        'amount' => 90000, // 15 * 6000
    ]);
});

it('fails when targeted cost layer has insufficient remaining', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 50,
        'cost' => 5000,
        'price' => 7500,
    ]);

    $layer = InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'unit_cost' => 5000,
        'acquired_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 20,
        'cost_layer_id' => $layer->id,
        'note' => 'Too many',
    ]);

    $response->assertStatus(500);

    // Layer should be unchanged
    expect($layer->fresh()->remaining_quantity)->toBe(10);
});

it('falls back to FIFO when cost_layer_id is not provided on remove', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create([
        'stock' => 30,
        'cost' => 5000,
        'price' => 7500,
    ]);

    // Layer 1 (oldest) should be consumed first
    $layer1 = InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'unit_cost' => 4000,
        'acquired_at' => now()->subDays(2),
    ]);

    $layer2 = InventoryCostLayer::query()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'remaining_quantity' => 20,
        'unit_cost' => 6000,
        'acquired_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/inventory/adjustments', [
        'product_id' => $product->id,
        'type' => 'remove',
        'quantity' => 5,
        'note' => 'Normal FIFO remove',
    ])->assertCreated();

    // FIFO: layer 1 consumed first
    expect($layer1->fresh()->remaining_quantity)->toBe(5);
    expect($layer2->fresh()->remaining_quantity)->toBe(20);
});
