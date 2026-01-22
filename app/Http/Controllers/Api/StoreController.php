<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StoreController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of stores the user has access to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $ownedStores = Store::query()
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (Store $store) => $store->setAttribute('user_role', 'owner'));

        $assignedStores = $user->assignedStores()
            ->get()
            ->map(fn (Store $store) => $store->setAttribute('user_role', $store->pivot->role));

        $stores = $ownedStores->merge($assignedStores);

        return StoreResource::collection($stores);
    }

    /**
     * Store a newly created store.
     */
    public function store(StoreStoreRequest $request): JsonResponse
    {
        $store = Store::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'code' => strtoupper($request->validated('code')),
            'address' => $request->validated('address'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'is_active' => $request->boolean('is_active', true),
            'settings' => $request->validated('settings'),
        ]);

        $store->setAttribute('user_role', 'owner');

        return (new StoreResource($store))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified store.
     */
    public function show(Request $request, Store $store): StoreResource
    {
        $this->authorize('view', $store);

        $store->loadCount(['orders', 'customers', 'storeProducts']);
        $store->setAttribute('user_role', $store->getUserRole($request->user()));

        return new StoreResource($store);
    }

    /**
     * Update the specified store.
     */
    public function update(UpdateStoreRequest $request, Store $store): StoreResource
    {
        $this->authorize('update', $store);

        $store->update($request->validated());

        $store->setAttribute('user_role', $store->getUserRole($request->user()));

        return new StoreResource($store);
    }

    /**
     * Remove the specified store.
     */
    public function destroy(Request $request, Store $store): Response
    {
        $this->authorize('delete', $store);

        $store->delete();

        return response()->noContent();
    }
}
