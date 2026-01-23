<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\WebhookService;

class OrderObserver
{
    public function __construct(private readonly WebhookService $webhookService) {}

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $this->webhookService->send('order:created', $order->toArray());
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $this->webhookService->send('order:updated', $order->toArray());
    }
}
