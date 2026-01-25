<?php

namespace App\Observers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\WebhookService;

class ProductObserver
{
    public function __construct(private readonly WebhookService $webhookService) {}

    /**
     * Get complete product data for socket broadcast.
     * Uses ProductResource to ensure consistent data format with API responses.
     */
    private function getProductData(Product $product): array
    {
        // Load relationships needed for complete product data
        $product->load(['category', 'bulkPrices', 'user.vendorProfile']);

        // Use ProductResource to format data consistently with API
        $resource = new ProductResource($product);

        return $resource->toArray(request());
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->webhookService->send('product:created', $this->getProductData($product));
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->webhookService->send('product:updated', $this->getProductData($product));

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
