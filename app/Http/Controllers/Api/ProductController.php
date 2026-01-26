<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStockDecrementRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateProductStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        tags: ['Product'],
        summary: 'List products with search and filters (public endpoint)',
        description: 'Public endpoint for e-commerce browsing. Use store_id or user_id filter for POS mode.',
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'store_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by store ID (for POS mode)'),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by vendor/owner ID'),
            new OA\Parameter(name: 'min_price', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'max_price', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'in_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                                    new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                                    new OA\Property(
                                        property: 'category',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 3),
                                            new OA\Property(property: 'name', type: 'string', example: 'Grocery'),
                                        ]
                                    ),
                                    new OA\Property(property: 'price', type: 'integer', example: 1250),
                                    new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                                    new OA\Property(property: 'stock', type: 'integer', example: 18),
                                    new OA\Property(property: 'is_low_stock', type: 'boolean', example: true),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 120),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with(['category', 'bulkPrices', 'user.vendorProfile'])
            ->where('is_active', true);

        if (! $request->filled('store_id')) {
            $query->where('is_ecommerce', true);
        }

        // Filter by store_id if provided (for POS mode - products available at specific store)
        if ($request->filled('store_id')) {
            $storeId = $request->integer('store_id');
            $query->whereHas('storeProducts', function ($q) use ($storeId) {
                $q->where('store_id', $storeId)
                    ->where('is_available', true);
            });
        }

        // Filter by user_id (vendor/owner) if provided
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $search = $request->string('search')->trim();
        if ($search->isNotEmpty()) {
            $term = '%'.$search->value().'%';
            $query->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhereHas('category', function ($query) use ($term) {
                        $query->where('name', 'like', $term);
                    });
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->integer('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->integer('max_price'));
        }

        if ($request->filled('in_stock')) {
            $inStock = filter_var($request->input('in_stock'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($inStock === true) {
                $query->where('stock', '>', 0);
            }
            if ($inStock === false) {
                $query->where('stock', '=', 0);
            }
        }

        if ($request->filled('stock_lte')) {
            $query->where('stock', '<=', $request->integer('stock_lte'));
        }

        if ($request->filled('stock_gte')) {
            $query->where('stock', '>=', $request->integer('stock_gte'));
        }

        if ($request->filled('has_barcode')) {
            $hasBarcode = filter_var($request->input('has_barcode'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($hasBarcode === true) {
                $query->whereNotNull('barcode');
            }
            if ($hasBarcode === false) {
                $query->whereNull('barcode');
            }
        }

        if ($request->filled('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        if ($request->filled('category')) {
            $categorySlug = $request->string('category')->value();
            $category = Category::query()->where('slug', $categorySlug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        $allowedSorts = ['name', 'price', 'stock', 'created_at'];
        $sort = $request->string('sort')->value();
        $direction = strtolower($request->string('direction', 'desc')->value());

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $products = $query->orderBy($sort, $direction)->paginate($perPage);

        return ProductResource::collection($products);
    }

    #[OA\Post(
        path: '/api/products',
        tags: ['Product'],
        summary: 'Create a product',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name', 'sku', 'category_id', 'price', 'currency', 'stock'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                        new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                        new OA\Property(property: 'category_id', type: 'integer', example: 3),
                        new OA\Property(property: 'price', type: 'integer', example: 1250),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'stock', type: 'integer', example: 18),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: 'is_ecommerce', type: 'boolean', example: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', description: 'Product image file'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Product created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                        new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                        new OA\Property(
                            property: 'category',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 3),
                                new OA\Property(property: 'name', type: 'string', example: 'Grocery'),
                            ]
                        ),
                        new OA\Property(property: 'price', type: 'integer', example: 1250),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'stock', type: 'integer', example: 18),
                        new OA\Property(property: 'is_low_stock', type: 'boolean', example: true),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['currency'] = $data['currency'] ?? 'PHP';

        if (array_key_exists('price', $data)) {
            $data['price'] = $this->normalizeMoney($request->input('price'));
        }

        if (array_key_exists('cost', $data) && $data['cost'] !== null) {
            $data['cost'] = $this->normalizeMoney($request->input('cost'));
        }

        unset($data['category']);

        $bulkPricing = $data['bulk_pricing'] ?? null;
        unset($data['bulk_pricing']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        } else {
            unset($data['image']);
        }

        $product = Product::query()->create($data);

        if ($bulkPricing && is_array($bulkPricing)) {
            foreach ($bulkPricing as $tier) {
                $product->bulkPrices()->create([
                    'min_qty' => $tier['min_qty'],
                    'price' => $this->normalizeMoney($tier['price']),
                ]);
            }
        }

        return (new ProductResource($product->load(['category', 'bulkPrices'])))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/products/{product}',
        tags: ['Product'],
        summary: 'Get a single product (public endpoint)',
        description: 'Public endpoint to view product details. Only returns active e-commerce products.',
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                        new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                        new OA\Property(
                            property: 'category',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 3),
                                new OA\Property(property: 'name', type: 'string', example: 'Grocery'),
                            ]
                        ),
                        new OA\Property(property: 'price', type: 'integer', example: 1250),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'stock', type: 'integer', example: 18),
                        new OA\Property(property: 'is_low_stock', type: 'boolean', example: true),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $product): ProductResource
    {
        $product = Product::query()
            ->with(['category', 'bulkPrices', 'user.vendorProfile'])
            ->where('is_active', true)
            ->where('is_ecommerce', true)
            ->findOrFail($product);

        return new ProductResource($product);
    }

    #[OA\Patch(
        path: '/api/products/{product}',
        tags: ['Product'],
        summary: 'Update a product',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                        new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                        new OA\Property(property: 'category_id', type: 'integer', example: 3),
                        new OA\Property(property: 'price', type: 'integer', example: 1250),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'stock', type: 'integer', example: 18),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: 'is_ecommerce', type: 'boolean', example: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', description: 'Product image file'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                        new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                        new OA\Property(
                            property: 'category',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 3),
                                new OA\Property(property: 'name', type: 'string', example: 'Grocery'),
                            ]
                        ),
                        new OA\Property(property: 'price', type: 'integer', example: 1250),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'stock', type: 'integer', example: 18),
                        new OA\Property(property: 'is_low_stock', type: 'boolean', example: true),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateProductRequest $request, int $product): ProductResource
    {
        $product = $this->findProduct($request, $product);
        $data = $request->validated();

        if (array_key_exists('price', $data)) {
            $data['price'] = $this->normalizeMoney($request->input('price'));
        }

        if (array_key_exists('cost', $data) && $data['cost'] !== null) {
            $data['cost'] = $this->normalizeMoney($request->input('cost'));
        }

        unset($data['category']);

        $bulkPricing = $data['bulk_pricing'] ?? null;
        unset($data['bulk_pricing']);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        } else {
            unset($data['image']);
        }

        $product->update($data);

        if ($bulkPricing !== null && is_array($bulkPricing)) {
            $product->bulkPrices()->delete();
            foreach ($bulkPricing as $tier) {
                $product->bulkPrices()->create([
                    'min_qty' => $tier['min_qty'],
                    'price' => $this->normalizeMoney($tier['price']),
                ]);
            }
        }

        return new ProductResource($product->load(['category', 'bulkPrices']));
    }

    #[OA\Delete(
        path: '/api/products/{product}',
        tags: ['Product'],
        summary: 'Delete a product',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, int $product): Response
    {
        $product = $this->findProduct($request, $product);
        $product->delete();

        return response()->noContent();
    }

    protected function findProduct(Request $request, int $productId): Product
    {
        return Product::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($productId);
    }

    protected function normalizeMoney(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    #[OA\Get(
        path: '/api/products/sku/{sku}',
        tags: ['Product'],
        summary: 'Get a product by SKU (public endpoint)',
        description: 'Public endpoint to find product by SKU. Only returns active e-commerce products.',
        parameters: [
            new OA\Parameter(name: 'sku', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'store_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by store ID'),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by vendor/owner ID'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function showBySku(Request $request, string $sku): ProductResource
    {
        $query = Product::query()
            ->with(['category', 'bulkPrices', 'user.vendorProfile'])
            ->where('is_active', true);

        if (! $request->filled('store_id')) {
            $query->where('is_ecommerce', true);
        }

        $query->where('sku', $sku);

        // Optional: filter by store_id
        if ($request->filled('store_id')) {
            $storeId = $request->integer('store_id');
            $query->whereHas('storeProducts', function ($q) use ($storeId) {
                $q->where('store_id', $storeId)
                    ->where('is_available', true);
            });
        }

        // Optional: filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $product = $query->firstOrFail();

        return new ProductResource($product);
    }

    #[OA\Get(
        path: '/api/products/barcode/{code}',
        tags: ['Product'],
        summary: 'Get a product by barcode (public endpoint)',
        description: 'Public endpoint to find product by barcode. Only returns active e-commerce products.',
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'store_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by store ID'),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by vendor/owner ID'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function showByBarcode(Request $request, string $code): ProductResource
    {
        $query = Product::query()
            ->with(['category', 'bulkPrices', 'user.vendorProfile'])
            ->where('is_active', true);

        if (! $request->filled('store_id')) {
            $query->where('is_ecommerce', true);
        }

        $query->where('barcode', $code);

        // Optional: filter by store_id
        if ($request->filled('store_id')) {
            $storeId = $request->integer('store_id');
            $query->whereHas('storeProducts', function ($q) use ($storeId) {
                $q->where('store_id', $storeId)
                    ->where('is_available', true);
            });
        }

        // Optional: filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $product = $query->firstOrFail();

        return new ProductResource($product);
    }

    #[OA\Patch(
        path: '/api/products/{product}/stock',
        tags: ['Product'],
        summary: 'Update product stock',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['stock'],
                properties: [
                    new OA\Property(property: 'stock', type: 'integer', example: 50),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Stock updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateStock(UpdateProductStockRequest $request, int $product): ProductResource
    {
        $product = $this->findProduct($request, $product);
        $product->update(['stock' => $request->validated('stock')]);

        return new ProductResource($product->load('category'));
    }

    #[OA\Post(
        path: '/api/products/bulk-stock-decrement',
        tags: ['Product'],
        summary: 'Decrement stock for multiple products',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'productId', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'variantSku', type: 'string', example: null),
                            ]
                        )
                    ),
                    new OA\Property(property: 'orderId', type: 'string', example: 'ORD-001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Stock decremented'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function bulkStockDecrement(BulkStockDecrementRequest $request): JsonResponse
    {
        $items = $request->validated('items');
        $userId = $request->user()->id;
        $results = [];
        $errors = [];

        DB::transaction(function () use ($items, $userId, &$results, &$errors) {
            foreach ($items as $item) {
                $product = Product::query()
                    ->where('user_id', $userId)
                    ->find($item['productId']);

                if (! $product) {
                    $errors[] = [
                        'productId' => $item['productId'],
                        'error' => 'Product not found',
                    ];

                    continue;
                }

                if ($product->stock < $item['quantity']) {
                    $errors[] = [
                        'productId' => $item['productId'],
                        'error' => 'Insufficient stock',
                        'available' => $product->stock,
                        'requested' => $item['quantity'],
                    ];

                    continue;
                }

                $product->decrement('stock', $item['quantity']);
                $results[] = [
                    'productId' => $item['productId'],
                    'newStock' => $product->fresh()->stock,
                ];
            }
        });

        return response()->json([
            'success' => count($errors) === 0,
            'data' => [
                'updated' => $results,
                'errors' => $errors,
            ],
        ]);
    }
}
