<?php

namespace App\Traits;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait HasStoreContext
{
    /**
     * Get the current store from the request context.
     */
    protected function currentStore(Request $request): ?Store
    {
        return $request->attributes->get('store') ?? (app()->bound('current.store') ? app('current.store') : null);
    }

    /**
     * Require a store context, abort if not set.
     */
    protected function requireStore(Request $request): Store
    {
        $store = $this->currentStore($request);

        if (! $store) {
            abort(400, 'Store context is required. Provide X-Store-Id header or store_id parameter.');
        }

        return $store;
    }

    /**
     * Get all stores the current user has access to.
     *
     * @return Collection<int, Store>
     */
    protected function userStores(Request $request): Collection
    {
        $user = $request->user();

        if (! $user) {
            return collect();
        }

        return $user->allAccessibleStores();
    }

    /**
     * Get the default store for the user (first owned store or first assigned store).
     */
    protected function defaultStore(Request $request): ?Store
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return $user->ownedStores()->first() ?? $user->assignedStores()->first();
    }

    /**
     * Resolve the store from request or use default for single-store users.
     */
    protected function resolveStore(Request $request): ?Store
    {
        $store = $this->currentStore($request);

        if ($store) {
            return $store;
        }

        $user = $request->user();

        if (! $user) {
            return null;
        }

        $stores = $user->allAccessibleStores();

        if ($stores->count() === 1) {
            return $stores->first();
        }

        return null;
    }
}
