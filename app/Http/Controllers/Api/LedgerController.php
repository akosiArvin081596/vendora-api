<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLedgerEntryRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Services\FifoCostService;
use App\Traits\HasStoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class LedgerController extends Controller
{
    use HasStoreContext;

    public function __construct(public FifoCostService $fifoCostService) {}

    #[OA\Get(
        path: '/api/ledger',
        tags: ['Ledger'],
        summary: 'List ledger entries',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'product_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ledger entries list'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LedgerEntry::query()
            ->with(['product'])
            ->where('user_id', $request->user()->id);

        $store = $this->currentStore($request);
        if ($store) {
            $query->where('store_id', $store->id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->value());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->value());
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->value());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->value());
        }

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $request->input('updated_since'));
        }

        $search = $request->string('search')->trim();
        if ($search->isNotEmpty()) {
            $term = '%'.$search->value().'%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('product', function ($q) use ($term) {
                        $q->where('name', 'like', $term);
                    });
            });
        }

        $perPage = $request->integer('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $entries = $query->orderByDesc('created_at')->paginate($perPage);

        return LedgerEntryResource::collection($entries);
    }

    #[OA\Get(
        path: '/api/ledger/summary',
        tags: ['Ledger'],
        summary: 'Ledger summary totals',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Ledger summary'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $store = $this->currentStore($request);

        $baseQuery = LedgerEntry::query()->where('user_id', $userId);
        if ($store) {
            $baseQuery->where('store_id', $store->id);
        }

        $totalStockIn = (clone $baseQuery)->where('type', 'stock_in')->sum('quantity');
        $totalStockOut = (clone $baseQuery)->where('type', 'stock_out')->sum(DB::raw('ABS(quantity)'));
        $totalRevenue = (clone $baseQuery)->where('type', 'sale')->sum('amount');
        $totalExpenses = (clone $baseQuery)->where('type', 'expense')->sum(DB::raw('ABS(amount)'));

        return response()->json([
            'data' => [
                'total_stock_in' => (int) $totalStockIn,
                'total_stock_out' => (int) $totalStockOut,
                'total_revenue' => (int) $totalRevenue,
                'total_expenses' => (int) $totalExpenses,
                'net_profit' => (int) ($totalRevenue - $totalExpenses),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/ledger',
        tags: ['Ledger'],
        summary: 'Create a manual ledger entry',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'description'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'stock_in'),
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', example: 10),
                    new OA\Property(property: 'amount', type: 'integer', example: 5000),
                    new OA\Property(property: 'description', type: 'string', example: 'Purchased new stock'),
                    new OA\Property(property: 'reference', type: 'string', example: 'PO-001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ledger entry created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreLedgerEntryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $store = $this->currentStore($request);

        $entry = DB::transaction(function () use ($data, $user, $store) {
            $type = $data['type'];
            $isInventory = $type === 'stock_in';
            $category = $isInventory ? 'inventory' : 'financial';

            $entryData = [
                'user_id' => $user->id,
                'store_id' => $store?->id,
                'product_id' => $data['product_id'] ?? null,
                'type' => $type,
                'category' => $category,
                'quantity' => $isInventory ? ($data['quantity'] ?? 0) : null,
                'amount' => ! $isInventory ? -abs($data['amount'] ?? 0) : null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
            ];

            // Update product stock if stock_in
            if ($isInventory && isset($data['product_id']) && isset($data['quantity'])) {
                $product = Product::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->findOrFail($data['product_id']);

                $product->update(['stock' => $product->stock + $data['quantity']]);
                $entryData['balance_qty'] = $product->stock;

                $entry = LedgerEntry::query()->create($entryData);

                $this->fifoCostService->createLayer([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'quantity' => $data['quantity'],
                    'unit_cost' => $product->cost ?? $product->price,
                    'reference' => $entry->reference ?? 'LEDGER-'.$entry->id,
                ]);

                return $entry;
            }

            return LedgerEntry::query()->create($entryData);
        });

        return (new LedgerEntryResource($entry->load('product')))
            ->response()
            ->setStatusCode(201);
    }
}
