<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\WebhookService;

class ProductObserver
{
    public function __construct(private readonly WebhookService $webhookService) {}

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->webhookService->send('product:created', $product->toArray());
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->webhookService->send('product:updated', $product->toArray());

        if ($product->wasChanged('stock')) {
            $this->webhookService->send('stock:updated', [
                'productId' => $product->id,
                'newStock' => $product->stock,
            ]);
        }
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->webhookService->send('product:deleted', ['id' => $product->id]);
    }
}
