<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardWidgetRequest;
use App\Http\Resources\DashboardChannelResource;
use App\Http\Resources\DashboardInventoryHealthResource;
use App\Http\Resources\DashboardKpiResource;
use App\Http\Resources\DashboardLowStockAlertsResource;
use App\Http\Resources\DashboardPaymentMethodsResource;
use App\Http\Resources\DashboardPendingOrdersResource;
use App\Http\Resources\DashboardRecentActivityResource;
use App\Http\Resources\DashboardSalesTrendResource;
use App\Http\Resources\DashboardTopProductsResource;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/dashboard/kpis',
        tags: ['Dashboard'],
        summary: 'Dashboard KPI cards',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'KPI summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'start_date', type: 'string', example: '2026-01-05'),
                        new OA\Property(property: 'end_date', type: 'string', example: '2026-01-11'),
                        new OA\Property(property: 'total_sales', type: 'integer', example: 128420),
                        new OA\Property(property: 'total_orders', type: 'integer', example: 214),
                        new OA\Property(property: 'net_revenue', type: 'integer', example: 96880),
                        new OA\Property(property: 'average_order_value', type: 'integer', example: 600),
                        new OA\Property(property: 'items_sold', type: 'integer', example: 1248),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function kpis(DashboardWidgetRequest $request): DashboardKpiResource
    {
        $range = $this->resolveDateRange($request);
        $userId = $request->user()->id;

        $orders = Order::query()
            ->where('user_id', $userId)
            ->whereBetween('ordered_at', [$range['start_date'], $range['end_date']]);

        $totalOrders = (int) (clone $orders)->count();
        $totalSales = (int) (clone $orders)->sum('total');
        $itemsSold = (int) (clone $orders)->sum('items_count');
        $averageOrderValue = $totalOrders > 0 ? (int) round($totalSales / $totalOrders) : 0;

        return new DashboardKpiResource([
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'net_revenue' => $totalSales,
            'average_order_value' => $averageOrderValue,
            'items_sold' => $itemsSold,
            'currency' => 'PHP',
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/sales-trend',
        tags: ['Dashboard'],
        summary: 'Sales trend lines',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sales trend',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'start_date', type: 'string', example: '2026-01-05'),
                        new OA\Property(property: 'end_date', type: 'string', example: '2026-01-11'),
                        new OA\Property(
                            property: 'labels',
                            type: 'array',
                            items: new OA\Items(type: 'string', example: '2026-01-05')
                        ),
                        new OA\Property(
                            property: 'series',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'pos'),
                                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'integer')),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'channel_definition',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'pos', type: 'string', example: 'Cash or card payments.'),
                                new OA\Property(property: 'online', type: 'string', example: 'Online payments.'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function salesTrend(DashboardWidgetRequest $request): DashboardSalesTrendResource
    {
        $range = $this->resolveDateRange($request);
        $userId = $request->user()->id;

        $labels = $this->buildDateLabels($range['start'], $range['end']);
        $series = [
            'pos' => array_fill_keys($labels, 0),
            'online' => array_fill_keys($labels, 0),
        ];

        $rows = Payment::query()
            ->selectRaw('DATE(paid_at) as paid_date, method, SUM(amount) as total')
            ->where('user_id', $userId)
            ->whereBetween('paid_at', [$range['start'], $range['end']])
            ->groupBy('paid_date', 'method')
            ->get();

        foreach ($rows as $row) {
            $date = (string) $row->paid_date;
            $channel = $this->channelForMethod((string) $row->method);

            if (array_key_exists($date, $series[$channel])) {
                $series[$channel][$date] += (int) $row->total;
            }
        }

        return new DashboardSalesTrendResource([
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'labels' => $labels,
            'series' => [
                ['name' => 'pos', 'data' => array_values($series['pos'])],
                ['name' => 'online', 'data' => array_values($series['online'])],
            ],
            'channel_definition' => $this->channelDefinition(),
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/orders-by-channel',
        tags: ['Dashboard'],
        summary: 'Orders by channel',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Orders by channel',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'start_date', type: 'string', example: '2026-01-05'),
                        new OA\Property(property: 'end_date', type: 'string', example: '2026-01-11'),
                        new OA\Property(property: 'total_orders', type: 'integer', example: 120),
                        new OA\Property(
                            property: 'channels',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'channel', type: 'string', example: 'pos'),
                                    new OA\Property(property: 'orders_count', type: 'integer', example: 74),
                                    new OA\Property(property: 'percentage', type: 'number', example: 61.67),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'channel_definition',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'pos', type: 'string', example: 'Cash or card payments.'),
                                new OA\Property(property: 'online', type: 'string', example: 'Online payments.'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function ordersByChannel(DashboardWidgetRequest $request): DashboardChannelResource
    {
        $range = $this->resolveDateRange($request);
        $userId = $request->user()->id;

        $counts = Payment::query()
            ->selectRaw("CASE WHEN method = 'online' THEN 'online' ELSE 'pos' END as channel")
            ->selectRaw('COUNT(DISTINCT order_id) as orders_count')
            ->where('user_id', $userId)
            ->whereBetween('paid_at', [$range['start'], $range['end']])
            ->groupBy('channel')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->channel => (int) $row->orders_count]);

        $posCount = (int) ($counts['pos'] ?? 0);
        $onlineCount = (int) ($counts['online'] ?? 0);
        $totalOrders = $posCount + $onlineCount;

        return new DashboardChannelResource([
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'total_orders' => $totalOrders,
            'channels' => [
                [
                    'channel' => 'pos',
                    'orders_count' => $posCount,
                    'percentage' => $this->percentage($posCount, $totalOrders),
                ],
                [
                    'channel' => 'online',
                    'orders_count' => $onlineCount,
                    'percentage' => $this->percentage($onlineCount, $totalOrders),
                ],
            ],
            'channel_definition' => $this->channelDefinition(),
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/payment-methods',
        tags: ['Dashboard'],
        summary: 'Payment methods distribution',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment methods distribution',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'start_date', type: 'string', example: '2026-01-05'),
                        new OA\Property(property: 'end_date', type: 'string', example: '2026-01-11'),
                        new OA\Property(property: 'total_amount', type: 'integer', example: 96880),
                        new OA\Property(
                            property: 'methods',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'method', type: 'string', example: 'cash'),
                                    new OA\Property(property: 'amount', type: 'integer', example: 45200),
                                    new OA\Property(property: 'payments_count', type: 'integer', example: 56),
                                    new OA\Property(property: 'percentage', type: 'number', example: 46.67),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function paymentMethods(DashboardWidgetRequest $request): DashboardPaymentMethodsResource
    {
        $range = $this->resolveDateRange($request);
        $userId = $request->user()->id;

        $rows = Payment::query()
            ->select('method')
            ->selectRaw('COUNT(*) as payments_count')
            ->selectRaw('SUM(amount) as amount')
            ->where('user_id', $userId)
            ->whereBetween('paid_at', [$range['start'], $range['end']])
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $totalAmount = (int) $rows->sum('amount');
        $methods = $rows->map(function ($row) use ($totalAmount) {
            $amount = (int) $row->amount;

            return [
                'method' => (string) $row->method,
                'amount' => $amount,
                'payments_count' => (int) $row->payments_count,
                'percentage' => $this->percentage($amount, $totalAmount),
            ];
        })->values()->all();

        return new DashboardPaymentMethodsResource([
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'total_amount' => $totalAmount,
            'methods' => $methods,
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/top-products',
        tags: ['Dashboard'],
        summary: 'Top selling products',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Top products',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'start_date', type: 'string', example: '2026-01-05'),
                        new OA\Property(property: 'end_date', type: 'string', example: '2026-01-11'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Premium Rice 5kg'),
                                    new OA\Property(property: 'units_sold', type: 'integer', example: 52),
                                    new OA\Property(property: 'revenue', type: 'integer', example: 15600),
                                    new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function topProducts(DashboardWidgetRequest $request): DashboardTopProductsResource
    {
        $range = $this->resolveDateRange($request);
        $userId = $request->user()->id;
        $limit = $this->resolveLimit($request, 5);

        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.user_id', $userId)
            ->whereBetween('orders.ordered_at', [$range['start_date'], $range['end_date']])
            ->select('products.id as product_id', 'products.name')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'units_sold' => (int) $row->units_sold,
                'revenue' => (int) $row->revenue,
                'currency' => 'PHP',
            ])
            ->all();

        return new DashboardTopProductsResource([
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'items' => $items,
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/inventory-health',
        tags: ['Dashboard'],
        summary: 'Inventory health breakdown',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Inventory health',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_items', type: 'integer', example: 156),
                        new OA\Property(
                            property: 'breakdown',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'status', type: 'string', example: 'in_stock'),
                                    new OA\Property(property: 'count', type: 'integer', example: 122),
                                    new OA\Property(property: 'percentage', type: 'number', example: 78.21),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function inventoryHealth(DashboardWidgetRequest $request): DashboardInventoryHealthResource
    {
        $userId = $request->user()->id;

        $totals = Product::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN stock > min_stock THEN 1 ELSE 0 END) as in_stock')
            ->selectRaw('SUM(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 ELSE 0 END) as low_stock')
            ->selectRaw('SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->first();

        $totalItems = (int) ($totals?->total_items ?? 0);
        $inStock = (int) ($totals?->in_stock ?? 0);
        $lowStock = (int) ($totals?->low_stock ?? 0);
        $outOfStock = (int) ($totals?->out_of_stock ?? 0);

        return new DashboardInventoryHealthResource([
            'total_items' => $totalItems,
            'breakdown' => [
                [
                    'status' => 'in_stock',
                    'count' => $inStock,
                    'percentage' => $this->percentage($inStock, $totalItems),
                ],
                [
                    'status' => 'low_stock',
                    'count' => $lowStock,
                    'percentage' => $this->percentage($lowStock, $totalItems),
                ],
                [
                    'status' => 'out_of_stock',
                    'count' => $outOfStock,
                    'percentage' => $this->percentage($outOfStock, $totalItems),
                ],
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/low-stock-alerts',
        tags: ['Dashboard'],
        summary: 'Low stock alerts',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Low stock alerts',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'PVC Pipe 1 inch'),
                                    new OA\Property(property: 'stock', type: 'integer', example: 4),
                                    new OA\Property(property: 'min_stock', type: 'integer', example: 10),
                                    new OA\Property(property: 'status', type: 'string', example: 'low_stock'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function lowStockAlerts(DashboardWidgetRequest $request): DashboardLowStockAlertsResource
    {
        $userId = $request->user()->id;
        $limit = $this->resolveLimit($request, 5);

        $items = Product::query()
            ->where('user_id', $userId)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => $product->stock,
                'min_stock' => $product->min_stock,
                'status' => $product->stock === 0 ? 'out_of_stock' : 'low_stock',
            ])
            ->all();

        return new DashboardLowStockAlertsResource([
            'items' => $items,
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/pending-orders',
        tags: ['Dashboard'],
        summary: 'Pending orders list',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pending orders',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'order_number', type: 'string', example: 'ORD-10492'),
                                    new OA\Property(property: 'customer', type: 'string', example: 'Michael S.'),
                                    new OA\Property(property: 'ordered_at', type: 'string', example: '2026-01-10'),
                                    new OA\Property(property: 'items_count', type: 'integer', example: 3),
                                    new OA\Property(property: 'total', type: 'integer', example: 2560),
                                    new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function pendingOrders(DashboardWidgetRequest $request): DashboardPendingOrdersResource
    {
        $userId = $request->user()->id;
        $limit = $this->resolveLimit($request, 5);

        $items = Order::query()
            ->with('customer')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->orderByDesc('ordered_at')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer' => $order->customer?->name,
                'ordered_at' => $order->ordered_at instanceof Carbon
                    ? $order->ordered_at->toDateString()
                    : (string) $order->ordered_at,
                'items_count' => $order->items_count,
                'total' => $order->total,
                'currency' => $order->currency,
                'status' => $order->status,
            ])
            ->all();

        return new DashboardPendingOrdersResource([
            'items' => $items,
        ]);
    }

    #[OA\Get(
        path: '/api/dashboard/recent-activity',
        tags: ['Dashboard'],
        summary: 'Recent activity feed',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Recent activity',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'action', type: 'string', example: 'create'),
                                    new OA\Property(property: 'model_type', type: 'string', example: 'App\\Models\\Order'),
                                    new OA\Property(property: 'model_id', type: 'integer', example: 42),
                                    new OA\Property(property: 'message', type: 'string', example: 'Create Order #42'),
                                    new OA\Property(property: 'created_at', type: 'string', example: '2026-01-11 09:10:00'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function recentActivity(DashboardWidgetRequest $request): DashboardRecentActivityResource
    {
        $userId = $request->user()->id;
        $limit = $this->resolveLimit($request, 10);

        $items = AuditLog::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'model_type' => $log->model_type,
                'model_id' => $log->model_id,
                'message' => $this->formatAuditMessage($log),
                'created_at' => $log->created_at?->toDateTimeString(),
            ])
            ->all();

        return new DashboardRecentActivityResource([
            'items' => $items,
        ]);
    }

    /**
     * @return array{start: Carbon, end: Carbon, start_date: string, end_date: string}
     */
    private function resolveDateRange(DashboardWidgetRequest $request): array
    {
        $end = $request->filled('end_date')
            ? Carbon::createFromFormat('Y-m-d', $request->string('end_date')->value())->endOfDay()
            : now()->endOfDay();

        $start = $request->filled('start_date')
            ? Carbon::createFromFormat('Y-m-d', $request->string('start_date')->value())->startOfDay()
            : $end->copy()->subDays(6)->startOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildDateLabels(Carbon $start, Carbon $end): array
    {
        $labels = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $labels[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $labels;
    }

    /**
     * @return array{pos: string, online: string}
     */
    private function channelDefinition(): array
    {
        return [
            'pos' => 'Cash or card payments.',
            'online' => 'Online payments.',
        ];
    }

    private function channelForMethod(string $method): string
    {
        return $method === 'online' ? 'online' : 'pos';
    }

    private function resolveLimit(DashboardWidgetRequest $request, int $default): int
    {
        $limit = $request->integer('limit', $default);

        return max(1, min(25, $limit));
    }

    private function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }

    private function formatAuditMessage(AuditLog $log): string
    {
        if (! $log->model_type) {
            return ucfirst($log->action);
        }

        $modelName = class_basename($log->model_type);

        if ($log->model_id) {
            return ucfirst($log->action).' '.$modelName.' #'.$log->model_id;
        }

        return ucfirst($log->action).' '.$modelName;
    }
}
