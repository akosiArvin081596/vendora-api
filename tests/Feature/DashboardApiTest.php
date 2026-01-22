<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns dashboard kpis for a date range', function () {
    $user = User::factory()->create();

    Order::factory()->for($user)->create([
        'ordered_at' => '2026-01-08',
        'items_count' => 3,
        'total' => 500,
    ]);
    Order::factory()->for($user)->create([
        'ordered_at' => '2026-01-10',
        'items_count' => 2,
        'total' => 700,
    ]);
    Order::factory()->for($user)->create([
        'ordered_at' => '2026-01-01',
        'items_count' => 1,
        'total' => 900,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/dashboard/kpis?start_date=2026-01-08&end_date=2026-01-10');

    $response->assertSuccessful();
    $response->assertJsonPath('data.total_orders', 2);
    $response->assertJsonPath('data.total_sales', 1200);
    $response->assertJsonPath('data.net_revenue', 1200);
    $response->assertJsonPath('data.average_order_value', 600);
    $response->assertJsonPath('data.items_sold', 5);
});

it('splits orders by channel using payment methods', function () {
    $user = User::factory()->create();

    $onlineOrder = Order::factory()->for($user)->create(['ordered_at' => '2026-01-09']);
    $posOrderOne = Order::factory()->for($user)->create(['ordered_at' => '2026-01-09']);
    $posOrderTwo = Order::factory()->for($user)->create(['ordered_at' => '2026-01-10']);

    Payment::factory()->for($user)->create([
        'order_id' => $onlineOrder->id,
        'paid_at' => '2026-01-09 10:00:00',
        'method' => 'online',
        'amount' => 100,
    ]);
    Payment::factory()->for($user)->create([
        'order_id' => $posOrderOne->id,
        'paid_at' => '2026-01-09 11:00:00',
        'method' => 'cash',
        'amount' => 150,
    ]);
    Payment::factory()->for($user)->create([
        'order_id' => $posOrderTwo->id,
        'paid_at' => '2026-01-10 12:00:00',
        'method' => 'card',
        'amount' => 200,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/dashboard/orders-by-channel?start_date=2026-01-09&end_date=2026-01-10');

    $response->assertSuccessful();
    $response->assertJsonPath('data.total_orders', 3);
    $response->assertJsonPath('data.channels.0.channel', 'pos');
    $response->assertJsonPath('data.channels.0.orders_count', 2);
    $response->assertJsonPath('data.channels.1.channel', 'online');
    $response->assertJsonPath('data.channels.1.orders_count', 1);
    $response->assertJsonPath('data.channels.0.percentage', 66.67);
    $response->assertJsonPath('data.channels.1.percentage', 33.33);
});

it('returns the top products by revenue', function () {
    $user = User::factory()->create();
    $productA = Product::factory()->for($user)->create(['name' => 'Rice']);
    $productB = Product::factory()->for($user)->create(['name' => 'Noodles']);

    $order = Order::factory()->for($user)->create(['ordered_at' => '2026-01-10']);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $productA->id,
        'quantity' => 2,
        'unit_price' => 100,
        'line_total' => 200,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $productB->id,
        'quantity' => 1,
        'unit_price' => 500,
        'line_total' => 500,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/dashboard/top-products?start_date=2026-01-10&end_date=2026-01-10&limit=1');

    $response->assertSuccessful();
    $response->assertJsonPath('data.items.0.product_id', $productB->id);
    $response->assertJsonPath('data.items.0.units_sold', 1);
    $response->assertJsonPath('data.items.0.revenue', 500);
});

it('returns recent activity items', function () {
    $user = User::factory()->create();

    AuditLog::query()->create([
        'user_id' => $user->id,
        'action' => 'create',
        'model_type' => Order::class,
        'model_id' => 5,
        'old_values' => null,
        'new_values' => ['status' => 'pending'],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/dashboard/recent-activity?limit=1');

    $response->assertSuccessful();
    $response->assertJsonPath('data.items.0.action', 'create');
    $response->assertJsonPath('data.items.0.message', 'Create Order #5');
});

it('validates dashboard date ranges', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/dashboard/kpis?start_date=2026-01-11&end_date=2026-01-10');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['end_date']);
});
