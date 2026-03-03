<?php

use App\Enums\StoreRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── Create and Assign Staff ──────────────────────────────────────

it('creates a new user and assigns as staff', function () {
    $owner = User::factory()->vendor()->create();
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'Jane Cashier',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'phone' => '+63 912 000 0000',
        'role' => 'cashier',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'Jane Cashier',
        'email' => 'jane@example.com',
        'phone' => '+63 912 000 0000',
        'role' => 'cashier',
        'role_label' => 'Cashier',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'user_type' => UserType::Cashier->value,
        'status' => UserStatus::Active->value,
    ]);

    $this->assertDatabaseHas('store_user', [
        'store_id' => $store->id,
        'role' => 'cashier',
    ]);
});

it('creates staff with custom permissions', function () {
    $owner = User::factory()->vendor()->create();
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $customPerms = ['products.view', 'orders.view', 'orders.create'];

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'Custom Staff',
        'email' => 'custom@example.com',
        'password' => 'password123',
        'role' => 'staff',
        'permissions' => $customPerms,
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'custom_permissions' => $customPerms,
        'effective_permissions' => $customPerms,
    ]);
});

it('maps store roles to correct user types', function () {
    $owner = User::factory()->vendor()->create();
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $mappings = [
        'manager' => UserType::Manager->value,
        'cashier' => UserType::Cashier->value,
        'staff' => UserType::Cashier->value,
    ];

    foreach ($mappings as $role => $expectedUserType) {
        $email = "test-{$role}@example.com";

        $this->postJson("/api/stores/{$store->id}/staff/create", [
            'name' => "Test {$role}",
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'user_type' => $expectedUserType,
        ]);
    }
});

// ── Validation ───────────────────────────────────────────────────

it('validates required fields for staff creation', function () {
    $owner = User::factory()->vendor()->create();
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
});

it('rejects duplicate email on staff creation', function () {
    $owner = User::factory()->vendor()->create();
    $store = Store::factory()->for($owner)->create();
    User::factory()->create(['email' => 'taken@example.com']);

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'Duplicate',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'role' => 'cashier',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

it('rejects invalid role on staff creation', function () {
    $owner = User::factory()->vendor()->create();
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'Bad Role',
        'email' => 'badrole@example.com',
        'password' => 'password123',
        'role' => 'owner',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['role']);
});

it('rejects invalid permission strings', function () {
    $owner = User::factory()->vendor()->create();
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'Bad Perms',
        'email' => 'badperms@example.com',
        'password' => 'password123',
        'role' => 'cashier',
        'permissions' => ['products.view', 'nonexistent.permission'],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['permissions.1']);
});

// ── Authorization ────────────────────────────────────────────────

it('allows manager to create staff', function () {
    $owner = User::factory()->vendor()->create();
    $manager = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($manager->id, [
        'role' => 'manager',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($manager);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'New Staff',
        'email' => 'newstaff@example.com',
        'password' => 'password123',
        'role' => 'cashier',
    ]);

    $response->assertCreated();
});

it('forbids cashier from creating staff', function () {
    $owner = User::factory()->vendor()->create();
    $cashier = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($cashier->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($cashier);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'Blocked',
        'email' => 'blocked@example.com',
        'password' => 'password123',
        'role' => 'staff',
    ]);

    $response->assertForbidden();
});

it('forbids unrelated user from creating staff', function () {
    $owner = User::factory()->vendor()->create();
    $stranger = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($stranger);

    $response = $this->postJson("/api/stores/{$store->id}/staff/create", [
        'name' => 'Blocked',
        'email' => 'stranger-staff@example.com',
        'password' => 'password123',
        'role' => 'cashier',
    ]);

    $response->assertForbidden();
});

// ── Permission Override in Policy ────────────────────────────────

it('uses custom permissions when set on pivot', function () {
    $owner = User::factory()->vendor()->create();
    $staff = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    // Cashier role defaults don't include inventory.adjust
    // but we give it via custom permissions
    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'permissions' => json_encode(['products.view', 'inventory.adjust']),
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($staff);

    $this->assertTrue(
        app(\App\Policies\StorePolicy::class)->adjustInventory($staff, $store)
    );
});

it('denies permission removed from custom override', function () {
    $owner = User::factory()->vendor()->create();
    $staff = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    // Cashier role defaults include orders.create, but custom override excludes it
    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'permissions' => json_encode(['products.view']),
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($staff);

    $this->assertFalse(
        app(\App\Policies\StorePolicy::class)->createOrders($staff, $store)
    );
});

it('falls back to role defaults when no custom permissions', function () {
    $owner = User::factory()->vendor()->create();
    $staff = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($staff->id, [
        'role' => 'cashier',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($staff);

    // Cashier defaults include orders.create
    $this->assertTrue(
        app(\App\Policies\StorePolicy::class)->createOrders($staff, $store)
    );

    // Cashier defaults don't include inventory.adjust
    $this->assertFalse(
        app(\App\Policies\StorePolicy::class)->adjustInventory($staff, $store)
    );
});

// ── Enhanced Resource Response ───────────────────────────────────

it('returns enhanced staff resource with permission fields', function () {
    $owner = User::factory()->vendor()->create();
    $staff = User::factory()->create(['phone' => '+63 999 000 0000']);
    $store = Store::factory()->for($owner)->create();

    $store->staff()->attach($staff->id, [
        'role' => 'manager',
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson("/api/stores/{$store->id}/staff");

    $response->assertSuccessful();
    $data = $response->json('data.0');

    expect($data)->toHaveKeys([
        'id', 'name', 'email', 'phone', 'role', 'role_label',
        'role_default_permissions', 'custom_permissions', 'effective_permissions',
        'assigned_at', 'created_at', 'updated_at',
    ]);

    expect($data['role_label'])->toBe('Manager');
    expect($data['custom_permissions'])->toBeNull();
    expect($data['effective_permissions'])->toBe(StoreRole::Manager->permissions());
});

// ── Existing add-by-email endpoint regression ────────────────────

it('still adds existing user by email as staff', function () {
    $owner = User::factory()->vendor()->create();
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $store = Store::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/stores/{$store->id}/staff", [
        'email' => 'existing@example.com',
        'role' => 'cashier',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'id' => $existing->id,
        'role' => 'cashier',
    ]);
});
