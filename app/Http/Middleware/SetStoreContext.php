<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStoreContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $storeId = $request->header('X-Store-Id') ?? $request->input('store_id');

        if ($storeId) {
            $store = Store::find($storeId);

            if (! $store) {
                return response()->json([
                    'message' => 'Store not found.',
                ], 404);
            }

            $user = $request->user();

            if ($user && ! $this->userCanAccessStore($user, $store)) {
                return response()->json([
                    'message' => 'Access denied to this store.',
                ], 403);
            }

            app()->instance('current.store', $store);
            $request->attributes->set('store', $store);
        }

        return $next($request);
    }

    /**
     * Check if user can access the given store.
     */
    protected function userCanAccessStore($user, Store $store): bool
    {
        return $store->user_id === $user->id ||
            $store->staff()->where('users.id', $user->id)->exists();
    }
}
