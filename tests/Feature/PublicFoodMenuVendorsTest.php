<?php

use App\Models\FoodMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns vendors that have available food menu items', function () {
    $vendor = User::factory()->vendor()->create();
    FoodMenuItem::factory()->for($vendor)->create();

    $response = $this->getJson('/api/food-menu/public/vendors');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vendor->id)
        ->assertJsonPath('data.0.name', $vendor->name);
});

it('excludes vendors with only unavailable items', function () {
    $vendor = User::factory()->vendor()->create();
    FoodMenuItem::factory()->for($vendor)->unavailable()->create();

    $response = $this->getJson('/api/food-menu/public/vendors');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('excludes vendors with only sold out items', function () {
    $vendor = User::factory()->vendor()->create();
    FoodMenuItem::factory()->for($vendor)->soldOut()->create();

    $response = $this->getJson('/api/food-menu/public/vendors');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('returns empty array when no vendors have food menus', function () {
    $response = $this->getJson('/api/food-menu/public/vendors');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('uses vendor profile business name when available', function () {
    $vendor = User::factory()->vendor()->create();
    $vendor->vendorProfile()->create(['business_name' => 'Tasty Kitchen']);
    FoodMenuItem::factory()->for($vendor)->create();

    $response = $this->getJson('/api/food-menu/public/vendors');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Tasty Kitchen');
});
