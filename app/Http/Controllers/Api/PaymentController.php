<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PaymentSummaryResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    #[OA\Get(
        path: '/api/payments',
        tags: ['Payment'],
        summary: 'List payments',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'method', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'payment_number', type: 'string', example: 'PAY-001'),
                                    new OA\Property(property: 'order_number', type: 'string', example: 'ORD-001'),
                                    new OA\Property(property: 'customer', type: 'string', example: 'John Dela Cruz'),
                                    new OA\Property(property: 'paid_at', type: 'string', example: '2026-01-10 14:30'),
                                    new OA\Property(property: 'amount', type: 'integer', example: 2450),
                                    new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                                    new OA\Property(property: 'method', type: 'string', example: 'cash'),
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
        $query = Payment::query()
            ->with(['order.customer'])
            ->where('user_id', $request->user()->id);

        $search = $request->string('search')->trim();
        if ($search->isNotEmpty()) {
            $term = '%'.$search->value().'%';
            $query->where(function ($query) use ($term) {
                $query->where('payment_number', 'like', $term)
                    ->orWhereHas('order', function ($query) use ($term) {
                        $query->where('order_number', 'like', $term)
                            ->orWhereHas('customer', function ($query) use ($term) {
                                $query->where('name', 'like', $term);
                            });
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('method')) {
            $query->where('method', $request->string('method')->value());
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $payments = $query->orderByDesc('paid_at')->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    #[OA\Get(
        path: '/api/payments/summary',
        tags: ['Payment'],
        summary: 'Payment summary cards',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_revenue', type: 'integer', example: 125450),
                        new OA\Property(property: 'cash_payments', type: 'integer', example: 45200),
                        new OA\Property(property: 'card_payments', type: 'integer', example: 58750),
                        new OA\Property(property: 'online_payments', type: 'integer', example: 21500),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function summary(Request $request): PaymentSummaryResource
    {
        $userId = $request->user()->id;

        $totalRevenue = (int) Payment::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('amount');
        $cashPayments = (int) Payment::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('method', 'cash')
            ->sum('amount');
        $cardPayments = (int) Payment::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('method', 'card')
            ->sum('amount');
        $onlinePayments = (int) Payment::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('method', 'online')
            ->sum('amount');

        return new PaymentSummaryResource([
            'total_revenue' => $totalRevenue,
            'cash_payments' => $cashPayments,
            'card_payments' => $cardPayments,
            'online_payments' => $onlinePayments,
        ]);
    }

    #[OA\Post(
        path: '/api/payments',
        tags: ['Payment'],
        summary: 'Create a payment',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['order_id', 'paid_at', 'amount', 'method', 'status'],
                properties: [
                    new OA\Property(property: 'order_id', type: 'integer', example: 1),
                    new OA\Property(property: 'paid_at', type: 'string', example: '2026-01-10 14:30'),
                    new OA\Property(property: 'amount', type: 'integer', example: 2450),
                    new OA\Property(property: 'method', type: 'string', example: 'cash'),
                    new OA\Property(property: 'status', type: 'string', example: 'completed'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Payment created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'payment_number', type: 'string', example: 'PAY-001'),
                        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-001'),
                        new OA\Property(property: 'customer', type: 'string', example: 'John Dela Cruz'),
                        new OA\Property(property: 'paid_at', type: 'string', example: '2026-01-10 14:30'),
                        new OA\Property(property: 'amount', type: 'integer', example: 2450),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'method', type: 'string', example: 'cash'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $order = Order::query()
            ->where('user_id', $user->id)
            ->findOrFail($data['order_id']);

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'payment_number' => $this->nextPaymentNumber($user->id),
            'paid_at' => $data['paid_at'],
            'amount' => $data['amount'],
            'currency' => 'PHP',
            'method' => $data['method'],
            'status' => $data['status'],
        ]);

        return (new PaymentResource($payment->load('order.customer')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/payments/{payment}',
        tags: ['Payment'],
        summary: 'Get a single payment',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'payment_number', type: 'string', example: 'PAY-001'),
                        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-001'),
                        new OA\Property(property: 'customer', type: 'string', example: 'John Dela Cruz'),
                        new OA\Property(property: 'paid_at', type: 'string', example: '2026-01-10 14:30'),
                        new OA\Property(property: 'amount', type: 'integer', example: 2450),
                        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                        new OA\Property(property: 'method', type: 'string', example: 'cash'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, int $payment): PaymentResource
    {
        $payment = $this->findPayment($request, $payment)->load('order.customer');

        return new PaymentResource($payment);
    }

    #[OA\Patch(
        path: '/api/payments/{payment}',
        tags: ['Payment'],
        summary: 'Update a payment',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'paid_at', type: 'string', example: '2026-01-10 14:30'),
                    new OA\Property(property: 'amount', type: 'integer', example: 2450),
                    new OA\Property(property: 'method', type: 'string', example: 'card'),
                    new OA\Property(property: 'status', type: 'string', example: 'completed'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'payment_number', type: 'string', example: 'PAY-001'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdatePaymentRequest $request, int $payment): PaymentResource
    {
        $payment = $this->findPayment($request, $payment);
        $payment->update($request->validated());

        return new PaymentResource($payment->load('order.customer'));
    }

    #[OA\Delete(
        path: '/api/payments/{payment}',
        tags: ['Payment'],
        summary: 'Delete a payment',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, int $payment): Response
    {
        $payment = $this->findPayment($request, $payment);
        $payment->delete();

        return response()->noContent();
    }

    protected function findPayment(Request $request, int $paymentId): Payment
    {
        return Payment::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($paymentId);
    }

    protected function nextPaymentNumber(int $userId): string
    {
        $latest = Payment::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->value('payment_number');

        $latestNumber = $latest ? (int) str_replace('PAY-', '', $latest) : 0;
        $next = $latestNumber + 1;

        return 'PAY-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
