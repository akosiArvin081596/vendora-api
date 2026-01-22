<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists categories', function () {
    $user = User::factory()->create();
    Category::factory()->count(3)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/categories');

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
});

it('lists categories with product count', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    Product::factory()->count(5)->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/categories?with_count=true');

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $category->id,
        'product_count' => 5,
    ]);
});

it('filters categories by is_active', function () {
    $user = User::factory()->create();
    Category::factory()->create(['is_active' => true]);
    Category::factory()->create(['is_active' => false]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/categories?is_active=true');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('creates a category', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/categories', [
        'name' => 'Electronics',
        'description' => 'Electronic devices and accessories',
        'icon' => 'cpu',
        'is_active' => true,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'description' => 'Electronic devices and accessories',
        'icon' => 'cpu',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('categories', [
        'name' => 'Electronics',
        'slug' => 'electronics',
    ]);
});

it('auto-generates slug from name when creating category', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/categories', [
        'name' => 'Personal Care Items',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'slug' => 'personal-care-items',
    ]);
});

it('validates required name when creating category', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/categories', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});

it('prevents duplicate category name', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Electronics']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/categories', [
        'name' => 'Electronics',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});

it('shows a category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create([
        'name' => 'Groceries',
        'description' => 'Food items',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/categories/'.$category->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $category->id,
        'name' => 'Groceries',
        'description' => 'Food items',
    ]);
});

it('returns 404 for non-existent category', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/categories/99999');

    $response->assertNotFound();
});

it('updates a category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Old Name']);

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/categories/'.$category->id, [
        'name' => 'New Name',
        'description' => 'Updated description',
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'New Name',
        'description' => 'Updated description',
    ]);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'New Name',
    ]);
});

it('updates slug when name changes', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/categories/'.$category->id, [
        'name' => 'New Name',
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'slug' => 'new-name',
    ]);
});

it('prevents duplicate name when updating category', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Existing']);
    $category = Category::factory()->create(['name' => 'To Update']);

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/categories/'.$category->id, [
        'name' => 'Existing',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});

it('deletes a category without products', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/categories/'.$category->id);

    $response->assertNoContent();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

it('cannot delete a category with products', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    Product::factory()->for($user)->for($category)->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/categories/'.$category->id);

    $response->assertStatus(409);
    $response->assertJsonFragment([
        'message' => 'Cannot delete category with associated products.',
    ]);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

it('requires authentication to access categories', function () {
    $response = $this->getJson('/api/categories');

    $response->assertUnauthorized();
});

it('requires authentication to create category', function () {
    $response = $this->postJson('/api/categories', [
        'name' => 'Test Category',
    ]);

    $response->assertUnauthorized();
});

it('returns category fields correctly', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'description' => 'A test category',
        'icon' => 'shopping-cart',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/categories/'.$category->id);

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'description' => 'A test category',
        'icon' => 'shopping-cart',
        'is_active' => true,
    ]);
});
