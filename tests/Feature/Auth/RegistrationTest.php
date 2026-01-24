<?php

use App\Enums\UserType;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Buyer Registration Tests (Public)
|--------------------------------------------------------------------------
*/

it('allows public buyer registration with valid data', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'message',
        'user' => ['id', 'name', 'email', 'user_type'],
        'token',
        'token_type',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'user_type' => UserType::Buyer->value,
    ]);
});

it('creates buyer with correct user type', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Buyer',
        'email' => 'jane@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertCreated();
    expect($response->json('user.user_type'))->toBe('buyer');
});

it('requires name for buyer registration', function () {
    $response = $this->postJson('/api/auth/register', [
        'email' => 'test@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});

it('requires valid email for buyer registration', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'invalid-email',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

it('requires password confirmation for buyer registration', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'SecurePassword123!',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
});

/*
|--------------------------------------------------------------------------
| Admin Vendor Creation Tests
|--------------------------------------------------------------------------
*/

it('allows admin to create vendors', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/admin/vendors', [
        'name' => 'Vendor User',
        'email' => 'vendor@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'business_name' => 'Vendor Corp',
        'subscription_plan' => 'basic',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'message',
        'user' => [
            'id',
            'name',
            'email',
            'user_type',
            'vendor_profile' => [
                'id',
                'business_name',
                'subscription_plan',
            ],
        ],
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'vendor@example.com',
        'user_type' => UserType::Vendor->value,
    ]);

    $this->assertDatabaseHas('vendor_profiles', [
        'business_name' => 'Vendor Corp',
        'subscription_plan' => 'basic',
    ]);
});

it('prevents non-admin users from creating vendors', function () {
    $buyer = User::factory()->buyer()->create();

    $response = $this->actingAs($buyer)->postJson('/api/admin/vendors', [
        'name' => 'Vendor User',
        'email' => 'vendor@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'business_name' => 'Vendor Corp',
        'subscription_plan' => 'basic',
    ]);

    $response->assertForbidden();
});

it('prevents unauthenticated vendor creation', function () {
    $response = $this->postJson('/api/admin/vendors', [
        'name' => 'Vendor User',
        'email' => 'vendor@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'business_name' => 'Vendor Corp',
        'subscription_plan' => 'basic',
    ]);

    $response->assertUnauthorized();
});

it('requires business_name for vendor creation', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/admin/vendors', [
        'name' => 'Vendor User',
        'email' => 'vendor@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'subscription_plan' => 'basic',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['business_name']);
});

it('requires valid subscription_plan for vendor creation', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/admin/vendors', [
        'name' => 'Vendor User',
        'email' => 'vendor@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'business_name' => 'Vendor Corp',
        'subscription_plan' => 'invalid_plan',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['subscription_plan']);
});

/*
|--------------------------------------------------------------------------
| Login Tests with Vendor Profile
|--------------------------------------------------------------------------
*/

it('includes vendor_profile in login response for vendors', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->create([
        'user_id' => $vendor->id,
        'business_name' => 'Test Business',
        'subscription_plan' => 'premium',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $vendor->email,
        'password' => 'password',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('user.vendor_profile.business_name', 'Test Business');
    $response->assertJsonPath('user.vendor_profile.subscription_plan', 'premium');
});

it('does not include vendor_profile for buyers', function () {
    $buyer = User::factory()->buyer()->create();

    $response = $this->postJson('/api/auth/login', [
        'email' => $buyer->email,
        'password' => 'password',
    ]);

    $response->assertSuccessful();
    expect($response->json('user'))->not->toHaveKey('vendor_profile');
});

it('does not include vendor_profile for admins', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertSuccessful();
    expect($response->json('user'))->not->toHaveKey('vendor_profile');
});

/*
|--------------------------------------------------------------------------
| User Type Helper Method Tests
|--------------------------------------------------------------------------
*/

it('correctly identifies admin users', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isVendor())->toBeFalse();
    expect($admin->isBuyer())->toBeFalse();
});

it('correctly identifies vendor users', function () {
    $vendor = User::factory()->vendor()->create();

    expect($vendor->isVendor())->toBeTrue();
    expect($vendor->isAdmin())->toBeFalse();
    expect($vendor->isBuyer())->toBeFalse();
});

it('correctly identifies buyer users', function () {
    $buyer = User::factory()->buyer()->create();

    expect($buyer->isBuyer())->toBeTrue();
    expect($buyer->isAdmin())->toBeFalse();
    expect($buyer->isVendor())->toBeFalse();
});
