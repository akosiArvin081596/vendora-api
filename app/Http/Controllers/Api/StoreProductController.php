<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoreProductRequest;
use App\Http\Requests\UpdateStoreProductRequest;
use App\Http\Resources\StoreProductResource;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class StoreProductController extends Controller
{
    use AuthorizesRequests;

    #[OA\Get(
        path: '/api/stores/{store}/products',
        tags: ['Store Product'],
        summary: 'List products available at a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_available', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'low_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store product list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'product_id', type: 'integer', example: 5),
                                    new OA\Property(property: 'store_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'stock', type: 'integer', example: 50),
                                    new OA\Property(property: 'min_stock', type: 'integer', example: 10),
                                    new OA\Property(property: 'max_stock', type: 'integer', example: 100),
                                    new OA\Property(property: 'price_override', type: 'integer', example: 1500, nullable: true),
                                    new OA\Property(property: 'is_available', type: 'boolean', example: true),
                                    new OA\Property(
                                        property: 'product',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 5),
                                            new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                                            new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                                            new OA\Property(property: 'price', type: 'integer', example: 1250),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request, Store $store): AnonymousResourceCollection
    {
        $this->authorize('viewProducts', $store);

        $query = StoreProduct::query()
            ->where('store_id', $store->id)
            ->with('product.category')
            ->whereHas('product', function ($query) {
                $query->where('is_active', true);
            });

        $search = $request->string('search')->trim();
        if ($search->isNotEmpty()) {
            $term = '%'.$search->value().'%';
            $query->whereHas('product', function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            });
        }

        if ($request->filled('is_available')) {
            $query->where('is_available', $request->boolean('is_available'));
        }

        if ($request->filled('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        $allowedSorts = ['stock', 'created_at'];
        $sort = $request->string('sort')->value();
        $direction = strtolower($request->string('direction', 'desc')->value());

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $storeProducts = $query->orderBy($sort, $direction)->paginate($perPage);

        return StoreProductResource::collection($storeProducts);
    }

    #[OA\Post(
        path: '/api/stores/{store}/products',
        tags: ['Store Product'],
        summary: 'Add a product to a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_id'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 5),
                    new OA\Property(property: 'stock', type: 'integer', example: 50),
                    new OA\Property(property: 'min_stock', type: 'integer', example: 10),
                    new OA\Property(property: 'max_stock', type: 'integer', example: 100),
                    new OA\Property(property: 'price_override', type: 'integer', example: 1500),
                    new OA\Property(property: 'is_available', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Product added to store',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'product_id', type: 'integer', example: 5),
                        new OA\Property(property: 'store_id', type: 'integer', example: 1),
                        new OA\Property(property: 'stock', type: 'integer', example: 50),
                        new OA\Property(property: 'is_available', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation error or product already added'),
        ]
    )]
    public function store(StoreStoreProductRequest $request, Store $store): JsonResponse
    {
        $this->authorize('manageProducts', $store);

        $product = Product::query()
            ->where('user_id', $store->user_id)
            ->where('id', $request->validated('product_id'))
            ->first();

        if (! $product) {
            return response()->json([
                'message' => 'Product not found or does not belong to store owner.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($store->storeProducts()->where('product_id', $product->id)->exists()) {
            return response()->json([
                'message' => 'Product is already added to this store.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $storeProduct = StoreProduct::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock' => $request->validated('stock', 0),
            'min_stock' => $request->validated('min_stock'),
            'max_stock' => $request->validated('max_stock'),
            'price_override' => $request->validated('price_override'),
            'is_available' => $request->boolean('is_available', true),
        ]);

        $storeProduct->load('product.category');

        return (new StoreProductResource($storeProduct))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/stores/{store}/products/{product}',
        tags: ['Store Product'],
        summary: 'Get a specific store product',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store product details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'product_id', type: 'integer', example: 5),
                        new OA\Property(property: 'store_id', type: 'integer', example: 1),
                        new OA\Property(property: 'stock', type: 'integer', example: 50),
                        new OA\Property(property: 'min_stock', type: 'integer', example: 10),
                        new OA\Property(property: 'max_stock', type: 'integer', example: 100),
                        new OA\Property(property: 'price_override', type: 'integer', example: 1500, nullable: true),
                        new OA\Property(property: 'is_available', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Product not found in store'),
        ]
    )]
    public function show(Request $request, Store $store, Product $product): StoreProductResource|JsonResponse
    {
        $this->authorize('viewProducts', $store);

        $storeProduct = StoreProduct::query()
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->whereHas('product', function ($query) {
                $query->where('is_active', true);
            })
            ->with('product.category')
            ->first();

        if (! $storeProduct) {
            return response()->json([
                'message' => 'Product not found in this store.',
            ], Response::HTTP_NOT_FOUND);
        }

        return new StoreProductResource($storeProduct);
    }

    #[OA\Patch(
        path: '/api/stores/{store}/products/{product}',
        tags: ['Store Product'],
        summary: 'Update a store product',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'stock', type: 'integer', example: 75),
                    new OA\Property(property: 'min_stock', type: 'integer', example: 15),
                    new OA\Property(property: 'max_stock', type: 'integer', example: 150),
                    new OA\Property(property: 'price_override', type: 'integer', example: 1600),
                    new OA\Property(property: 'is_available', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store product updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'stock', type: 'integer', example: 75),
                        new OA\Property(property: 'is_available', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Product not found in store'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateStoreProductRequest $request, Store $store, Product $product): StoreProductResource|JsonResponse
    {
        $this->authorize('manageProducts', $store);

        $storeProduct = StoreProduct::query()
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->first();

        if (! $storeProduct) {
            return response()->json([
                'message' => 'Product not found in this store.',
            ], Response::HTTP_NOT_FOUND);
        }

        $storeProduct->update($request->validated());
        $storeProduct->load('product.category');

        return new StoreProductResource($storeProduct);
    }

    #[OA\Delete(
        path: '/api/stores/{store}/products/{product}',
        tags: ['Store Product'],
        summary: 'Remove a product from a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Product removed from store'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Product not found in store'),
        ]
    )]
    public function destroy(Request $request, Store $store, Product $product): Response|JsonResponse
    {
        $this->authorize('manageProducts', $store);

        $storeProduct = StoreProduct::query()
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->first();

        if (! $storeProduct) {
            return response()->json([
                'message' => 'Product not found in this store.',
            ], Response::HTTP_NOT_FOUND);
        }

        $storeProduct->delete();

        return response()->noContent();
    }
}
