<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function __construct(
        private readonly ?string $url = null,
        private readonly ?string $secret = null,
    ) {}

    /**
     * Send an event to the WebSocket server.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(string $event, array $data): void
    {
        $url = $this->url ?? config('services.websocket.url');
        $secret = $this->secret ?? config('services.websocket.secret');

        if (empty($url) || empty($secret)) {
            return;
        }

        $payload = [
            'event' => $event,
            'data' => $data,
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        try {
            Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Webhook delivery failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
