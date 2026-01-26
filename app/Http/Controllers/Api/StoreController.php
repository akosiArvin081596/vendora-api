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
use OpenApi\Attributes as OA;

class StoreController extends Controller
{
    use AuthorizesRequests;

    #[OA\Get(
        path: '/api/stores',
        tags: ['Store'],
        summary: 'List stores the user has access to',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Main Store'),
                                    new OA\Property(property: 'code', type: 'string', example: 'MAIN-001'),
                                    new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                                    new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                                    new OA\Property(property: 'email', type: 'string', example: 'store@example.com'),
                                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                    new OA\Property(property: 'user_role', type: 'string', example: 'owner'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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

    #[OA\Post(
        path: '/api/stores',
        tags: ['Store'],
        summary: 'Create a new store',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'code'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Main Store'),
                    new OA\Property(property: 'code', type: 'string', example: 'MAIN-001'),
                    new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                    new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'store@example.com'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'settings', type: 'object'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Store created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Main Store'),
                        new OA\Property(property: 'code', type: 'string', example: 'MAIN-001'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: 'user_role', type: 'string', example: 'owner'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
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

    #[OA\Get(
        path: '/api/stores/{store}',
        tags: ['Store'],
        summary: 'Get a single store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Main Store'),
                        new OA\Property(property: 'code', type: 'string', example: 'MAIN-001'),
                        new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                        new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                        new OA\Property(property: 'email', type: 'string', example: 'store@example.com'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: 'user_role', type: 'string', example: 'owner'),
                        new OA\Property(property: 'orders_count', type: 'integer', example: 150),
                        new OA\Property(property: 'customers_count', type: 'integer', example: 45),
                        new OA\Property(property: 'store_products_count', type: 'integer', example: 80),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, Store $store): StoreResource
    {
        $this->authorize('view', $store);

        $store->loadCount(['orders', 'customers', 'storeProducts']);
        $store->setAttribute('user_role', $store->getUserRole($request->user()));

        return new StoreResource($store);
    }

    #[OA\Patch(
        path: '/api/stores/{store}',
        tags: ['Store'],
        summary: 'Update a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Store Name'),
                    new OA\Property(property: 'code', type: 'string', example: 'MAIN-002'),
                    new OA\Property(property: 'address', type: 'string', example: '456 New St'),
                    new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 9999'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'newstore@example.com'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'settings', type: 'object'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Updated Store Name'),
                        new OA\Property(property: 'code', type: 'string', example: 'MAIN-002'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateStoreRequest $request, Store $store): StoreResource
    {
        $this->authorize('update', $store);

        $store->update($request->validated());

        $store->setAttribute('user_role', $store->getUserRole($request->user()));

        return new StoreResource($store);
    }

    #[OA\Delete(
        path: '/api/stores/{store}',
        tags: ['Store'],
        summary: 'Delete a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, Store $store): Response
    {
        $this->authorize('delete', $store);

        $store->delete();

        return response()->noContent();
    }
}
