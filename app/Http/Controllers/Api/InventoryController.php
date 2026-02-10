<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Resources\InventoryResource;
use App\Http\Resources\InventorySummaryResource;
use App\Models\InventoryAdjustment;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Services\FifoCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class InventoryController extends Controller
{
    public function __construct(public FifoCostService $fifoCostService) {}

    #[OA\Get(
        path: '/api/inventory',
        tags: ['Inventory'],
        summary: 'List inventory items',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Inventory list',
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
                                    new OA\Property(property: 'stock', type: 'integer', example: 18),
                                    new OA\Property(property: 'min_stock', type: 'integer', example: 10),
                                    new OA\Property(property: 'max_stock', type: 'integer', example: 50),
                                    new OA\Property(property: 'status', type: 'string', example: 'in_stock'),
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
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Product::query()
            ->with(['category', 'bulkPrices'])
            ->where('user_id', $user->id);

        $search = $request->string('search')->trim();
        if ($search->isNotEmpty()) {
            $term = '%'.$search->value().'%';
            $query->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            });
        }

        $status = $request->string('status')->value();
        $criticalThreshold = 2;

        if ($status === 'in_stock') {
            $query->whereColumn('stock', '>', 'min_stock');
        }

        if ($status === 'low_stock') {
            $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock');
        }

        if ($status === 'out_of_stock') {
            $query->where('stock', '=', 0);
        }

        if ($status === 'critical') {
            $query->where('stock', '<=', $criticalThreshold)->where('stock', '>', 0);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $products = $query->orderBy('name')->paginate($perPage);

        return InventoryResource::collection($products);
    }

    #[OA\Get(
        path: '/api/inventory/summary',
        tags: ['Inventory'],
        summary: 'Inventory summary cards',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Inventory summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_items', type: 'integer', example: 156),
                        new OA\Property(property: 'low_stock_items', type: 'integer', example: 8),
                        new OA\Property(property: 'out_of_stock_items', type: 'integer', example: 3),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function summary(Request $request): InventorySummaryResource
    {
        $userId = $request->user()->id;

        $totalItems = Product::query()->where('user_id', $userId)->count();
        $lowStockItems = Product::query()
            ->where('user_id', $userId)
            ->where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')
            ->count();
        $outOfStockItems = Product::query()
            ->where('user_id', $userId)
            ->where('stock', 0)
            ->count();

        return new InventorySummaryResource([
            'total_items' => $totalItems,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
        ]);
    }

    #[OA\Post(
        path: '/api/inventory/adjustments',
        tags: ['Inventory'],
        summary: 'Adjust stock for a product',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_id', 'type', 'quantity'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'type', type: 'string', example: 'add'),
                    new OA\Property(property: 'quantity', type: 'integer', example: 5),
                    new OA\Property(property: 'note', type: 'string', example: 'Manual adjustment'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Stock adjusted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Stock adjusted successfully.'),
                        new OA\Property(property: 'adjustment_id', type: 'integer', example: 12),
                        new OA\Property(
                            property: 'inventory',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                                new OA\Property(property: 'sku', type: 'string', example: 'GR-1001'),
                                new OA\Property(property: 'stock', type: 'integer', example: 23),
                                new OA\Property(property: 'min_stock', type: 'integer', example: 10),
                                new OA\Property(property: 'max_stock', type: 'integer', example: 50),
                                new OA\Property(property: 'status', type: 'string', example: 'in_stock'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeAdjustment(StoreInventoryAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = $this->findProduct($request, $data['product_id']);

        $quantity = (int) $data['quantity'];
        $type = $data['type'];

        if ($type !== 'set' && $quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero for add or remove.',
            ]);
        }

        $stockBefore = $product->stock;
        $stockAfter = match ($type) {
            'add' => $stockBefore + $quantity,
            'remove' => $stockBefore - $quantity,
            'set' => $quantity,
        };

        if ($stockAfter < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity exceeds current stock.',
            ]);
        }

        $unitCost = isset($data['unit_cost']) ? (int) round(((float) $data['unit_cost']) * 100) : null;
        $costLayerId = $data['cost_layer_id'] ?? null;

        $adjustment = DB::transaction(function () use ($request, $product, $type, $quantity, $stockBefore, $stockAfter, $data, $unitCost, $costLayerId) {
            $userId = $request->user()->id;

            $adjustment = InventoryAdjustment::query()->create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $unitCost,
                'note' => $data['note'] ?? null,
            ]);

            $ledgerAmount = 0;

            if ($type === 'add') {
                $layerCost = $unitCost ?? $product->cost ?? $product->price;
                $this->fifoCostService->createLayer([
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'quantity' => $quantity,
                    'unit_cost' => $layerCost,
                    'inventory_adjustment_id' => $adjustment->id,
                    'reference' => 'ADJ-'.$adjustment->id,
                ]);
                $ledgerAmount = $layerCost * $quantity;

                $weightedAvg = $this->fifoCostService->getWeightedAverageCost($product->id);
                $product->update([
                    'stock' => $stockAfter,
                    'cost' => $weightedAvg ?? $product->cost,
                ]);
            } elseif ($type === 'remove') {
                if ($costLayerId) {
                    $result = $this->fifoCostService->consumeSpecificLayer([
                        'cost_layer_id' => $costLayerId,
                        'quantity' => $quantity,
                        'inventory_adjustment_id' => $adjustment->id,
                    ]);
                } else {
                    $this->fifoCostService->ensureLayersExist($product, $userId);
                    $result = $this->fifoCostService->consumeLayers([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'inventory_adjustment_id' => $adjustment->id,
                    ]);
                }
                $ledgerAmount = $result['total_cost'];

                $product->update(['stock' => $stockAfter]);
            } else {
                // type === 'set'
                $this->fifoCostService->handleSetAdjustment(
                    $product, $userId, $stockBefore, $stockAfter, $unitCost, $adjustment->id,
                );

                $weightedAvg = $this->fifoCostService->getWeightedAverageCost($product->id);
                $product->update([
                    'stock' => $stockAfter,
                    'cost' => $weightedAvg ?? $product->cost,
                ]);

                $costPerUnit = $unitCost ?? $weightedAvg ?? $product->cost ?? $product->price;
                $ledgerAmount = $costPerUnit * abs($stockAfter - $stockBefore);
            }

            // Create ledger entry for the stock adjustment
            $ledgerType = match ($type) {
                'add' => 'stock_in',
                'remove' => 'stock_out',
                default => 'adjustment',
            };

            $ledgerQty = match ($type) {
                'add' => $quantity,
                'remove' => -$quantity,
                'set' => $stockAfter - $stockBefore,
            };

            $costPerUnit = $this->fifoCostService->getWeightedAverageCost($product->id) ?? $product->cost ?? $product->price;
            $balanceAmount = $costPerUnit * $stockAfter;

            LedgerEntry::query()->create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'type' => $ledgerType,
                'category' => 'inventory',
                'quantity' => $ledgerQty,
                'amount' => $ledgerAmount,
                'balance_qty' => $stockAfter,
                'balance_amount' => $balanceAmount,
                'reference' => 'ADJ-'.$adjustment->id,
                'description' => ($data['note'] ?? 'Stock adjustment').' ('.$type.' '.$quantity.')',
            ]);

            return $adjustment;
        });

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'adjustment_id' => $adjustment->id,
            'inventory' => new InventoryResource($product->fresh()),
        ], 201);
    }

    protected function findProduct(Request $request, int $productId): Product
    {
        return Product::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($productId);
    }
}
