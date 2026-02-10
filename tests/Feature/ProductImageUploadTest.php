<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('creates a product with an image', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    // Use create() with image mimetype instead of image() which requires GD
    $image = UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg');

    $response = $this->postJson('/api/products', [
        'name' => 'Product With Image',
        'sku' => 'IMG-001',
        'category_id' => $category->id,
        'price' => 1500,
        'currency' => 'PHP',
        'stock' => 50,
        'unit' => 'pc',
        'is_active' => true,
        'is_ecommerce' => true,
        'image' => $image,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'Product With Image',
        'sku' => 'IMG-001',
    ]);

    $product = Product::where('sku', 'IMG-001')->first();
    expect($product->image)->not->toBeNull();

    Storage::disk('public')->assertExists($product->image);
});

it('creates a product without an image', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'Product Without Image',
        'sku' => 'NOIMG-001',
        'category_id' => $category->id,
        'price' => 1500,
        'currency' => 'PHP',
        'stock' => 50,
        'unit' => 'pc',
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.image', null);

    $product = Product::where('sku', 'NOIMG-001')->first();
    expect($product->image)->toBeNull();
});

it('updates a product with a new image', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $image = UploadedFile::fake()->create('new-product.png', 100, 'image/png');

    $response = $this->postJson('/api/products/'.$product->id, [
        '_method' => 'PATCH',
        'name' => 'Updated With Image',
        'image' => $image,
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'Updated With Image',
    ]);

    $product->refresh();
    expect($product->image)->not->toBeNull();

    Storage::disk('public')->assertExists($product->image);
});

it('replaces existing image when updating', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $oldImage = UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg');
    $oldPath = $oldImage->store('products', 'public');

    $product = Product::factory()->for($user)->for($category)->create([
        'image' => $oldPath,
    ]);

    Storage::disk('public')->assertExists($oldPath);

    Sanctum::actingAs($user);

    $newImage = UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg');

    $response = $this->postJson('/api/products/'.$product->id, [
        '_method' => 'PATCH',
        'image' => $newImage,
    ]);

    $response->assertSuccessful();

    $product->refresh();
    expect($product->image)->not->toBe($oldPath);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($product->image);
});

it('returns full image URL in product resource', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $image = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');
    $path = $image->store('products', 'public');

    $product = Product::factory()->for($user)->for($category)->create([
        'image' => $path,
        'is_active' => true,
        'is_ecommerce' => true,
    ]);

    $response = $this->getJson('/api/products/'.$product->id);

    $response->assertSuccessful();
    $response->assertJsonPath('data.image', Storage::disk('public')->url($path));
})->skip('Public product endpoints temporarily disabled');

it('clears existing image when image is set to null', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $image = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');
    $path = $image->store('products', 'public');

    $product = Product::factory()->for($user)->for($category)->create([
        'image' => $path,
    ]);

    Storage::disk('public')->assertExists($path);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/products/'.$product->id, [
        'image' => null,
    ]);

    $response->assertSuccessful();

    $product->refresh();
    expect($product->image)->toBeNull();

    Storage::disk('public')->assertMissing($path);
});

it('keeps existing image when image field is not sent', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    $image = UploadedFile::fake()->create('keep.jpg', 100, 'image/jpeg');
    $path = $image->store('products', 'public');

    $product = Product::factory()->for($user)->for($category)->create([
        'image' => $path,
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/products/'.$product->id, [
        'name' => 'Renamed Product',
    ]);

    $response->assertSuccessful();

    $product->refresh();
    expect($product->name)->toBe('Renamed Product');
    expect($product->image)->toBe($path);

    Storage::disk('public')->assertExists($path);
});

it('validates image file type', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->postJson('/api/products', [
        'name' => 'Invalid Image Product',
        'sku' => 'INV-001',
        'category_id' => $category->id,
        'price' => 1500,
        'currency' => 'PHP',
        'stock' => 50,
        'unit' => 'pc',
        'is_active' => true,
        'is_ecommerce' => true,
        'image' => $invalidFile,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['image']);
});

it('validates image file size', function () {
    $user = User::factory()->vendor()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    // Create a file larger than 5MB (5120KB limit)
    $largeFile = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');

    $response = $this->postJson('/api/products', [
        'name' => 'Large Image Product',
        'sku' => 'LRG-001',
        'category_id' => $category->id,
        'price' => 1500,
        'currency' => 'PHP',
        'stock' => 50,
        'unit' => 'pc',
        'is_active' => true,
        'is_ecommerce' => true,
        'image' => $largeFile,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['image']);
});
