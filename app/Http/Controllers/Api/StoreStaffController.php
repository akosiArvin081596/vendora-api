<?php

namespace App\Http\Controllers\Api;

use App\Enums\StoreRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStoreStaffRequest;
use App\Http\Resources\StoreStaffResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class StoreStaffController extends Controller
{
    use AuthorizesRequests;

    #[OA\Get(
        path: '/api/stores/{store}/staff',
        tags: ['Store Staff'],
        summary: 'List staff members for a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Staff list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                    new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                    new OA\Property(property: 'role', type: 'string', example: 'cashier'),
                                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                                    new OA\Property(property: 'assigned_at', type: 'string', format: 'date-time'),
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
        $this->authorize('view', $store);

        $staff = $store->staff()->get();

        return StoreStaffResource::collection($staff);
    }

    #[OA\Post(
        path: '/api/stores/{store}/staff',
        tags: ['Store Staff'],
        summary: 'Add a staff member to a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'role'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'staff@example.com'),
                    new OA\Property(property: 'role', type: 'string', example: 'cashier'),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Staff member added',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'email', type: 'string', example: 'staff@example.com'),
                        new OA\Property(property: 'role', type: 'string', example: 'cashier'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error or user already staff'),
        ]
    )]
    public function store(StoreStaffRequest $request, Store $store): JsonResponse
    {
        $this->authorize('manageStaff', $store);

        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found with this email.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($store->user_id === $user->id) {
            return response()->json([
                'message' => 'Cannot add store owner as staff.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($store->staff()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is already a staff member of this store.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $store->staff()->attach($user->id, [
            'role' => $request->validated('role'),
            'permissions' => $request->validated('permissions'),
            'assigned_at' => now(),
        ]);

        $staff = $store->staff()->where('users.id', $user->id)->first();

        return (new StoreStaffResource($staff))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Patch(
        path: '/api/stores/{store}/staff/{user}',
        tags: ['Store Staff'],
        summary: 'Update a staff member\'s role',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'role', type: 'string', example: 'manager'),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Staff member updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'role', type: 'string', example: 'manager'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not a staff member'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateStoreStaffRequest $request, Store $store, User $user): StoreStaffResource|JsonResponse
    {
        $this->authorize('manageStaff', $store);

        if (! $store->staff()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is not a staff member of this store.',
            ], Response::HTTP_NOT_FOUND);
        }

        $store->staff()->updateExistingPivot($user->id, [
            'role' => $request->validated('role'),
            'permissions' => $request->validated('permissions'),
        ]);

        $staff = $store->staff()->where('users.id', $user->id)->first();

        return new StoreStaffResource($staff);
    }

    #[OA\Delete(
        path: '/api/stores/{store}/staff/{user}',
        tags: ['Store Staff'],
        summary: 'Remove a staff member from a store',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Staff member removed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not a staff member'),
        ]
    )]
    public function destroy(Request $request, Store $store, User $user): Response|JsonResponse
    {
        $this->authorize('manageStaff', $store);

        if (! $store->staff()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is not a staff member of this store.',
            ], Response::HTTP_NOT_FOUND);
        }

        $store->staff()->detach($user->id);

        return response()->noContent();
    }

    #[OA\Get(
        path: '/api/store-roles',
        tags: ['Store Staff'],
        summary: 'Get available store roles',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Available roles',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'value', type: 'string', example: 'cashier'),
                                    new OA\Property(property: 'label', type: 'string', example: 'Cashier'),
                                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function roles(): JsonResponse
    {
        $roles = collect(StoreRole::assignable())
            ->map(fn (StoreRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'permissions' => $role->permissions(),
            ]);

        return response()->json([
            'data' => $roles,
        ]);
    }
}
