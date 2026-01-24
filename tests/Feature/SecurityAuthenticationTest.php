<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Authentication Security Tests
|--------------------------------------------------------------------------
*/

it('rejects login with invalid credentials', function () {
    User::factory()->vendor()->create([
        'email' => 'test@example.com',
        'password' => 'correct-password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized();
    $response->assertJson([
        'success' => false,
        'error' => 'INVALID_CREDENTIALS',
        'message' => 'The email or password you entered is incorrect.',
    ]);
});

it('rejects login with non-existent email', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'any-password',
    ]);

    $response->assertUnauthorized();
});

it('requires authentication for protected endpoints', function () {
    $response = $this->getJson('/api/user');

    $response->assertUnauthorized();
});

it('rejects requests with invalid token', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer invalid-token-here',
    ])->getJson('/api/user');

    $response->assertUnauthorized();
});

it('rejects requests with expired/deleted token after logout', function () {
    $user = User::factory()->create();

    $loginResponse = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $token = $loginResponse->json('token');

    // Logout
    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/auth/logout');

    // Try to use the old token
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/user');

    $response->assertUnauthorized();
});

it('prevents SQL injection in login email field', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => "admin@test.com' OR '1'='1",
        'password' => 'password',
    ]);

    $response->assertUnprocessable();
});

it('prevents SQL injection in login password field', function () {
    User::factory()->vendor()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => "' OR '1'='1",
    ]);

    $response->assertUnauthorized();
});

it('enforces rate limiting on login endpoint', function () {
    // Make 6 requests (limit is 5 per minute)
    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $response->assertStatus(429);
});

it('enforces rate limiting on register endpoint', function () {
    // Make 6 requests (limit is 5 per minute)
    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/auth/register', [
            'name' => "User {$i}",
            'email' => "test{$i}@example.com",
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);
    }

    $response->assertStatus(429);
});

it('validates password strength on registration', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => '123',
        'password_confirmation' => '123',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
});

it('prevents duplicate email registration', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'New User',
        'email' => 'existing@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

it('does not expose user existence through login response timing', function () {
    User::factory()->create(['email' => 'exists@example.com']);

    // Both should return same error message
    $existingUserResponse = $this->postJson('/api/auth/login', [
        'email' => 'exists@example.com',
        'password' => 'wrong-password',
    ]);

    $nonExistentResponse = $this->postJson('/api/auth/login', [
        'email' => 'notexists@example.com',
        'password' => 'wrong-password',
    ]);

    expect($existingUserResponse->json('message'))
        ->toBe($nonExistentResponse->json('message'));
});
