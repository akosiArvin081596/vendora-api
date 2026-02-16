<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.websocket.url' => 'http://localhost:3001']);
    config(['services.websocket.secret' => 'test-secret']);
});

describe('WebhookService', function () {
    it('sends webhook with correct payload and signature', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $service = new WebhookService;
        $service->send('test:event', ['key' => 'value']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://localhost:3001'
                && $payload['event'] === 'test:event'
                && $payload['data']['key'] === 'value'
                && $request->hasHeader('X-Webhook-Signature')
                && $request->hasHeader('Content-Type', 'application/json');
        });
    });

    it('generates correct HMAC signature', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $service = new WebhookService;
        $service->send('test:event', ['key' => 'value']);

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $expectedSignature = hash_hmac('sha256', json_encode($payload), 'test-secret');

            return $request->header('X-Webhook-Signature')[0] === $expectedSignature;
        });
    });

    it('does not send webhook when url is not configured', function () {
        config(['services.websocket.url' => null]);
        Http::fake();

        $service = new WebhookService;
        $service->send('test:event', ['key' => 'value']);

        Http::assertNothingSent();
    });

    it('does not send webhook when secret is not configured', function () {
        config(['services.websocket.secret' => null]);
        Http::fake();

        $service = new WebhookService;
        $service->send('test:event', ['key' => 'value']);

        Http::assertNothingSent();
    });

    it('logs error when webhook delivery fails', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 500),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'Webhook delivery failed' && $context['event'] === 'test:event');

        Http::fake(function () {
            throw new Exception('Connection failed');
        });

        $service = new WebhookService;
        $service->send('test:event', ['key' => 'value']);
    });
});

describe('ProductObserver webhooks', function () {
    it('sends product:created webhook when product is created', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->for($user)->for($category)->create([
            'name' => 'Test Product',
        ]);

        Http::assertSent(function ($request) use ($product) {
            $payload = $request->data();

            return $payload['event'] === 'product:created'
                && $payload['data']['id'] === $product->id
                && $payload['data']['name'] === 'Test Product';
        });
    });

    it('sends product:updated webhook when product is updated', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create();

        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $product->update(['name' => 'Updated Product']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['event'] === 'product:updated'
                && $payload['data']['name'] === 'Updated Product';
        });
    });

    it('sends stock:updated webhook when stock changes', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create(['stock' => 10]);

        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $product->update(['stock' => 5]);

        Http::assertSent(function ($request) use ($product) {
            $payload = $request->data();

            return $payload['event'] === 'stock:updated'
                && $payload['data']['productId'] === $product->id
                && $payload['data']['newStock'] === 5;
        });
    });

    it('sends product:deleted webhook when product is deleted', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($user)->for($category)->create();
        $productId = $product->id;

        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $product->delete();

        Http::assertSent(function ($request) use ($productId) {
            $payload = $request->data();

            return $payload['event'] === 'product:deleted'
                && $payload['data']['id'] === $productId;
        });
    });
});

describe('OrderObserver webhooks', function () {
    it('sends order:created webhook when order is created', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'order_number' => 'ORD-001',
        ]);

        Http::assertSent(function ($request) use ($order) {
            $payload = $request->data();

            return $payload['event'] === 'order:created'
                && $payload['data']['id'] === $order->id
                && $payload['data']['order_number'] === 'ORD-001';
        });
    });

    it('sends order:updated webhook when order is updated', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => 'pending']);

        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $order->update(['status' => 'completed']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['event'] === 'order:updated'
                && $payload['data']['status'] === 'completed';
        });
    });
});

describe('CategoryObserver webhooks', function () {
    it('sends category:created webhook when category is created', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $category = Category::factory()->create([
            'name' => 'Electronics',
        ]);

        Http::assertSent(function ($request) use ($category) {
            $payload = $request->data();

            return $payload['event'] === 'category:created'
                && $payload['data']['id'] === $category->id
                && $payload['data']['name'] === 'Electronics';
        });
    });

    it('sends category:updated webhook when category is updated', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $category = Category::factory()->create();

        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $category->update(['name' => 'Updated Category']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['event'] === 'category:updated'
                && $payload['data']['name'] === 'Updated Category';
        });
    });

    it('sends category:deleted webhook when category is deleted', function () {
        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $category = Category::factory()->create();
        $categoryId = $category->id;

        Http::fake([
            'http://localhost:3001' => Http::response([], 200),
        ]);

        $category->delete();

        Http::assertSent(function ($request) use ($categoryId) {
            $payload = $request->data();

            return $payload['event'] === 'category:deleted'
                && $payload['data']['id'] === $categoryId;
        });
    });
});
