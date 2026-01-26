<?php

use App\Enums\UserType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds the admin user in the database seeder', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@vendora.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->user_type)->toBe(UserType::Admin);
    expect(Hash::check('password', $admin->password))->toBeTrue();
});
