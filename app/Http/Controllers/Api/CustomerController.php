<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerSummaryResource;
use App\Models\Customer;
use App\Traits\HasStoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class CustomerController extends Controller
{
    use HasStoreContext;

    #[OA\Get(
        path: '/api/customers',
        tags: ['Customer'],
        summary: 'List customers',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'John Dela Cruz'),
                                    new OA\Property(property: 'email', type: 'string', example: 'john.delacruz@email.com'),
                                    new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                                    new OA\Property(property: 'orders_count', type: 'integer', example: 15),
                                    new OA\Property(property: 'total_spent', type: 'integer', example: 15420),
                                    new OA\Property(property: 'status', type: 'string', example: 'active'),
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
                                new OA\Property(property: 'total', type: 'integer', example: 342),
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
        $query = Customer::query()->where('user_id', $request->user()->id);

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
                $query->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $allowedSorts = ['name', 'orders_count', 'total_spent', 'created_at'];
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

        $customers = $query->orderBy($sort, $direction)->paginate($perPage);

        return CustomerResource::collection($customers);
    }

    #[OA\Get(
        path: '/api/customers/summary',
        tags: ['Customer'],
        summary: 'Customer summary cards',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_customers', type: 'integer', example: 342),
                        new OA\Property(property: 'active', type: 'integer', example: 298),
                        new OA\Property(property: 'vip', type: 'integer', example: 24),
                        new OA\Property(property: 'inactive', type: 'integer', example: 20),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function summary(Request $request): CustomerSummaryResource
    {
        $userId = $request->user()->id;
        $store = $this->currentStore($request);

        $baseQuery = Customer::query()->where('user_id', $userId);
        if ($store) {
            $baseQuery->where('store_id', $store->id);
        }

        $totalCustomers = (clone $baseQuery)->count();
        $active = (clone $baseQuery)->where('status', 'active')->count();
        $vip = (clone $baseQuery)->where('status', 'vip')->count();
        $inactive = (clone $baseQuery)->where('status', 'inactive')->count();

        return new CustomerSummaryResource([
            'total_customers' => $totalCustomers,
            'active' => $active,
            'vip' => $vip,
            'inactive' => $inactive,
        ]);
    }

    #[OA\Post(
        path: '/api/customers',
        tags: ['Customer'],
        summary: 'Create a customer',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'status'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Dela Cruz'),
                    new OA\Property(property: 'email', type: 'string', example: 'john.delacruz@email.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Customer created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'John Dela Cruz'),
                        new OA\Property(property: 'email', type: 'string', example: 'john.delacruz@email.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                        new OA\Property(property: 'orders_count', type: 'integer', example: 15),
                        new OA\Property(property: 'total_spent', type: 'integer', example: 15420),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $customer = Customer::query()->create($data);

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/customers/{customer}',
        tags: ['Customer'],
        summary: 'Get a single customer',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'John Dela Cruz'),
                        new OA\Property(property: 'email', type: 'string', example: 'john.delacruz@email.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                        new OA\Property(property: 'orders_count', type: 'integer', example: 15),
                        new OA\Property(property: 'total_spent', type: 'integer', example: 15420),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, int $customer): CustomerResource
    {
        $customer = $this->findCustomer($request, $customer);

        return new CustomerResource($customer);
    }

    #[OA\Patch(
        path: '/api/customers/{customer}',
        tags: ['Customer'],
        summary: 'Update a customer',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Dela Cruz'),
                    new OA\Property(property: 'email', type: 'string', example: 'john.delacruz@email.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                    new OA\Property(property: 'status', type: 'string', example: 'vip'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'John Dela Cruz'),
                        new OA\Property(property: 'email', type: 'string', example: 'john.delacruz@email.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+63 912 345 6789'),
                        new OA\Property(property: 'orders_count', type: 'integer', example: 15),
                        new OA\Property(property: 'total_spent', type: 'integer', example: 15420),
                        new OA\Property(property: 'status', type: 'string', example: 'vip'),
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
    public function update(UpdateCustomerRequest $request, int $customer): CustomerResource
    {
        $customer = $this->findCustomer($request, $customer);
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    #[OA\Delete(
        path: '/api/customers/{customer}',
        tags: ['Customer'],
        summary: 'Delete a customer',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, int $customer): Response
    {
        $customer = $this->findCustomer($request, $customer);
        $customer->delete();

        return response()->noContent();
    }

    protected function findCustomer(Request $request, int $customerId): Customer
    {
        return Customer::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($customerId);
    }
}
