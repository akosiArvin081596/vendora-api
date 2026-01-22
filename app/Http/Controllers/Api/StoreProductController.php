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

class StoreProductController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of products available at a store.
     */
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

    /**
     * Add a product to a store.
     */
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

    /**
     * Display a specific store product.
     */
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

    /**
     * Update a store product.
     */
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

    /**
     * Remove a product from a store.
     */
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
