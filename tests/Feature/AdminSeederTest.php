<?php

use App\Enums\UserType;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds the admin user in the database seeder', function () {
    config([
        'app.admin.email' => 'owner@example.test',
        'app.admin.password' => 'correct-horse-battery-staple',
    ]);

    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'owner@example.test')->first();

    expect($admin)->not->toBeNull();
    expect($admin->user_type)->toBe(UserType::Admin);
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check('correct-horse-battery-staple', $admin->password))->toBeTrue();
    expect(User::query()->count())->toBe(1);
});

it('seeds no accounts unless both admin settings are set', function (?string $email, ?string $password) {
    config([
        'app.admin.email' => $email,
        'app.admin.password' => $password,
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
})->with([
    'neither' => [null, null],
    'email only' => ['owner@example.test', null],
    'password only' => [null, 'correct-horse-battery-staple'],
    'empty strings' => ['', ''],
]);

it('never seeds the old default password', function () {
    config([
        'app.admin.email' => 'owner@example.test',
        'app.admin.password' => 'correct-horse-battery-staple',
    ]);

    $this->seed(DatabaseSeeder::class);

    User::query()->each(function (User $user): void {
        expect(Hash::check('password', $user->password))->toBeFalse();
    });
});

it('says why it skipped the admin account', function () {
    config([
        'app.admin.email' => null,
        'app.admin.password' => null,
    ]);

    $this->artisan('db:seed', ['--class' => AdminSeeder::class])
        ->expectsOutputToContain('set ADMIN_EMAIL and ADMIN_PASSWORD')
        ->assertSuccessful();
});

it('updates the admin account on a second run instead of adding another', function () {
    config([
        'app.admin.email' => 'owner@example.test',
        'app.admin.password' => 'first-password-value',
    ]);
    $this->seed(AdminSeeder::class);

    config(['app.admin.password' => 'second-password-value']);
    $this->seed(AdminSeeder::class);

    $admin = User::query()->where('email', 'owner@example.test')->sole();

    expect(Hash::check('second-password-value', $admin->password))->toBeTrue();
});
