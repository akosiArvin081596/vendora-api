<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\WebhookService;

class CategoryObserver
{
    public function __construct(private readonly WebhookService $webhookService) {}

    /**
     * Handle the Category "created" event.
     */
    public function created(Category $category): void
    {
        $this->webhookService->send('category:created', $category->toArray());
    }

    /**
     * Handle the Category "updated" event.
     */
    public function updated(Category $category): void
    {
        $this->webhookService->send('category:updated', $category->toArray());
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        $this->webhookService->send('category:deleted', ['id' => $category->id]);
    }
}
