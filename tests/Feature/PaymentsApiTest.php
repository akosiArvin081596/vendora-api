<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists payments for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $payment = Payment::factory()->for($user)->create();
    Payment::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payments');

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => $payment->id,
        'payment_number' => $payment->payment_number,
    ]);
});

it('returns payment summary totals for completed payments', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    Payment::factory()->for($user)->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'method' => 'cash',
        'status' => 'completed',
    ]);
    Payment::factory()->for($user)->create([
        'order_id' => $order->id,
        'amount' => 2000,
        'method' => 'card',
        'status' => 'completed',
    ]);
    Payment::factory()->for($user)->create([
        'order_id' => $order->id,
        'amount' => 3000,
        'method' => 'online',
        'status' => 'completed',
    ]);
    Payment::factory()->for($user)->create([
        'order_id' => $order->id,
        'amount' => 4000,
        'method' => 'cash',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payments/summary');

    $response->assertSuccessful();
    $response->assertJsonPath('data.total_revenue', 6000);
    $response->assertJsonPath('data.cash_payments', 1000);
    $response->assertJsonPath('data.card_payments', 2000);
    $response->assertJsonPath('data.online_payments', 3000);
});
