<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderSummaryResource;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreProduct;
use App\Services\FifoCostService;
use App\Traits\HasStoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    use HasStoreContext;

    public function __construct(public FifoCostService $fifoCostService) {}

    #[OA\Get(
        path: '/api/orders',
        tags: ['Order'],
        summary: 'List orders',
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
                description: 'Order list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'order_number', type: 'string', example: 'ORD-001'),
                                    new OA\Property(property: 'customer', type: 'string', example: 'John Dela Cruz'),
                                    new OA\Property(property: 'ordered_at', type: 'string', example: '2026-01-10'),
                                    new OA\Property(property: 'items_count', type: 'integer', example: 5),
                                    new OA\Property(property: 'total', type: 'integer', example: 2450),
                                    new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                                    new OA\Property(property: 'status', type: 'string', example: 'completed'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 248),
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
        $query = Order::query()
            ->with(['customer', 'store'])
            ->where('user_id', $request->user()->id);

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $request->input('updated_since'));
        }

        // Filter by store if store context is provided
        $store = $this->currentStore($request);
        if ($store) {
            $query->where('store_id', $store->id);
        }

        $search = $request->string('search')->trim();
        if ($search->isNotEmpty()) {
            $term = '%'.$search->value().'%';
            $query->where(function ($query) use ($term) {
                $query->where('order_number', 'like', $term)
                    ->orWhereHas('customer', function ($query) use ($term) {
                        $query->where('name', 'like', $term);
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $orders = $query->orderByDesc('ordered_at')->paginate($perPage);

        return OrderResource::collection($orders);
    }

    #[OA\Get(
        path: '/api/orders/summary',
        tags: ['Order'],
        summary: 'Order summary cards',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_orders', type: 'integer', example: 248),
                        new OA\Property(property: 'pending', type: 'integer', example: 12),
                        new OA\Property(property: 'processing', type: 'integer', example: 8),
                        new OA\Property(property: 'completed', type: 'integer', example: 228),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function summary(Request $request): OrderSummaryResource
    {
        $userId = $request->user()->id;
        $store = $this->currentStore($request);

        $baseQuery = Order::query()->where('user_id', $userId);
        if ($store) {
            $baseQuery->where('store_id', $store->id);
        }

        $totalOrders = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $processing = (clone $baseQuery)->where('status', 'processing')->count();
        $completed = (clone $baseQuery)->where('status', 'completed')->count();

        return new OrderSummaryResource([
            'total_orders' => $totalOrders,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
        ]);
    }

    #[OA\Post(
        path: '/api/orders',
        tags: ['Order'],
        summary: 'Create an order',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ordered_at', 'status', 'items'],
                properties: [
                    new OA\Property(property: 'customer_id', type: 'integer', example: 1),
                    new OA\Property(property: 'ordered_at', type: 'string', example: '2026-01-10'),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Order created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-001'),
                        new OA\Property(property: 'customer', type: 'string', example: 'John Dela Cruz'),
                        new OA\Property(property: 'ordered_at', type: 'string', example: '2026-01-10'),
                        new OA\Property(property: 'items_count', type: 'integer', example: 5),
                        new OA\Property(property: 'total', type: 'integer', example: 2450),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $items = $data['items'];

        // Resolve store context - use provided store or default for single-store users
        $store = $this->resolveStore($request);

        $order = DB::transaction(function () use ($data, $user, $items, $store) {
            $orderNumber = $this->nextOrderNumber($user->id);
            $customerId = $data['customer_id'] ?? null;

            // If store context exists, validate customer belongs to that store
            $customerQuery = Customer::query()->where('user_id', $user->id);
            if ($store) {
                $customerQuery->where('store_id', $store->id);
            }
            $customer = $customerId ? $customerQuery->findOrFail($customerId) : null;

            $order = Order::query()->create([
                'user_id' => $user->id,
                'store_id' => $store?->id,
                'customer_id' => $customer?->id,
                'processed_by' => $user->id,
                'order_number' => $orderNumber,
                'ordered_at' => $data['ordered_at'],
                'status' => $data['status'],
                'items_count' => 0,
                'total' => 0,
                'currency' => 'PHP',
            ]);

            $productIds = collect($items)->pluck('product_id')->unique()->all();

            $lineTotal = 0;
            $itemsCount = 0;
            $totalCogs = 0;
            $stockOutData = [];

            // If store context exists, use store-specific inventory
            if ($store) {
                $storeProducts = StoreProduct::query()
                    ->where('store_id', $store->id)
                    ->whereIn('product_id', $productIds)
                    ->with('product')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                foreach ($items as $item) {
                    $storeProduct = $storeProducts->get($item['product_id']);
                    if (! $storeProduct || ! $storeProduct->is_available) {
                        throw ValidationException::withMessages([
                            'items' => 'One or more products are not available at this store.',
                        ]);
                    }

                    $quantity = (int) $item['quantity'];
                    if ($storeProduct->stock < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => 'Insufficient stock for '.$storeProduct->product->name.'.',
                        ]);
                    }

                    $unitPrice = $storeProduct->effective_price;

                    $orderItem = OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $storeProduct->product_id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'unit_cost' => 0,
                        'line_total' => $quantity * $unitPrice,
                    ]);

                    // FIFO consumption
                    $this->fifoCostService->ensureLayersExist($storeProduct->product, $user->id);
                    $fifoResult = $this->fifoCostService->consumeLayers([
                        'product_id' => $storeProduct->product_id,
                        'quantity' => $quantity,
                        'order_item_id' => $orderItem->id,
                    ]);

                    $orderItem->update(['unit_cost' => $fifoResult['weighted_average_cost']]);

                    $lineTotal += $quantity * $unitPrice;
                    $totalCogs += $fifoResult['total_cost'];
                    $itemsCount += $quantity;

                    $storeProduct->update([
                        'stock' => $storeProduct->stock - $quantity,
                    ]);

                    $stockOutData[] = [
                        'product_id' => $storeProduct->product_id,
                        'quantity' => $quantity,
                        'unit_cost' => $fifoResult['weighted_average_cost'],
                        'remaining_stock' => $storeProduct->stock - $quantity,
                    ];
                }
            } else {
                // Legacy behavior: use product stock directly
                $products = Product::query()
                    ->where('user_id', $user->id)
                    ->whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($items as $item) {
                    $product = $products->get($item['product_id']);
                    if (! $product) {
                        throw ValidationException::withMessages([
                            'items' => 'One or more products are invalid.',
                        ]);
                    }

                    $quantity = (int) $item['quantity'];
                    if ($product->stock < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => 'Insufficient stock for '.$product->name.'.',
                        ]);
                    }

                    $unitPrice = $product->price;

                    $orderItem = OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'unit_cost' => 0,
                        'line_total' => $quantity * $unitPrice,
                    ]);

                    // FIFO consumption
                    $this->fifoCostService->ensureLayersExist($product, $user->id);
                    $fifoResult = $this->fifoCostService->consumeLayers([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'order_item_id' => $orderItem->id,
                    ]);

                    $orderItem->update(['unit_cost' => $fifoResult['weighted_average_cost']]);

                    $lineTotal += $quantity * $unitPrice;
                    $totalCogs += $fifoResult['total_cost'];
                    $itemsCount += $quantity;

                    $product->update([
                        'stock' => $product->stock - $quantity,
                    ]);

                    $stockOutData[] = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_cost' => $fifoResult['weighted_average_cost'],
                        'remaining_stock' => $product->stock - $quantity,
                    ];
                }
            }

            $order->update([
                'items_count' => $itemsCount,
                'total' => $lineTotal,
            ]);

            if ($customer) {
                $customer->update([
                    'orders_count' => $customer->orders_count + 1,
                    'total_spent' => $customer->total_spent + $lineTotal,
                ]);
            }

            // Create ledger entry for the sale (revenue)
            LedgerEntry::query()->create([
                'user_id' => $user->id,
                'store_id' => $store?->id,
                'order_id' => $order->id,
                'type' => 'sale',
                'category' => 'financial',
                'quantity' => $itemsCount,
                'amount' => $lineTotal,
                'balance_amount' => $lineTotal,
                'reference' => $orderNumber,
                'description' => 'Sale '.$orderNumber,
            ]);

            // Create COGS expense ledger entry
            if ($totalCogs > 0) {
                LedgerEntry::query()->create([
                    'user_id' => $user->id,
                    'store_id' => $store?->id,
                    'order_id' => $order->id,
                    'type' => 'expense',
                    'category' => 'financial',
                    'amount' => -$totalCogs,
                    'reference' => $orderNumber,
                    'description' => 'COGS '.$orderNumber,
                ]);
            }

            // Create stock_out entries for each item
            foreach ($stockOutData as $stockItem) {
                $itemAmount = $stockItem['unit_cost'] * $stockItem['quantity'];

                LedgerEntry::query()->create([
                    'user_id' => $user->id,
                    'store_id' => $store?->id,
                    'product_id' => $stockItem['product_id'],
                    'order_id' => $order->id,
                    'type' => 'stock_out',
                    'category' => 'inventory',
                    'quantity' => -$stockItem['quantity'],
                    'amount' => $itemAmount,
                    'balance_qty' => $stockItem['remaining_stock'],
                    'reference' => $orderNumber,
                    'description' => 'Sold via '.$orderNumber,
                ]);
            }

            return $order;
        });

        return (new OrderResource($order->load(['customer', 'store', 'items.product'])))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/orders/{order}',
        tags: ['Order'],
        summary: 'Get a single order',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-001'),
                        new OA\Property(property: 'customer', type: 'string', example: 'John Dela Cruz'),
                        new OA\Property(property: 'ordered_at', type: 'string', example: '2026-01-10'),
                        new OA\Property(property: 'items_count', type: 'integer', example: 5),
                        new OA\Property(property: 'total', type: 'integer', example: 2450),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, int $order): OrderResource
    {
        $order = $this->findOrder($request, $order)->load(['customer', 'store', 'items.product']);

        return new OrderResource($order);
    }

    #[OA\Patch(
        path: '/api/orders/{order}',
        tags: ['Order'],
        summary: 'Update order status',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'processing'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-001'),
                        new OA\Property(property: 'status', type: 'string', example: 'processing'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateOrderRequest $request, int $order): OrderResource
    {
        $order = $this->findOrder($request, $order);
        $order->update($request->validated());

        return new OrderResource($order->load(['customer', 'items.product']));
    }

    #[OA\Delete(
        path: '/api/orders/{order}',
        tags: ['Order'],
        summary: 'Delete an order',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, int $order): Response
    {
        $order = $this->findOrder($request, $order);
        $order->delete();

        return response()->noContent();
    }

    protected function findOrder(Request $request, int $orderId): Order
    {
        $query = Order::query()
            ->where('user_id', $request->user()->id);

        $store = $this->currentStore($request);
        if ($store) {
            $query->where('store_id', $store->id);
        }

        return $query->findOrFail($orderId);
    }

    protected function nextOrderNumber(int $userId): string
    {
        $latest = Order::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->value('order_number');

        $latestNumber = $latest ? (int) str_replace('ORD-', '', $latest) : 0;
        $next = $latestNumber + 1;

        return 'ORD-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
