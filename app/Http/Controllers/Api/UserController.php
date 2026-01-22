<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/user',
        tags: ['User'],
        summary: 'Get the authenticated user with store information',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user with stores',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Vendor Corp'),
                        new OA\Property(property: 'business_name', type: 'string', example: 'Vendor Corp'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'vendor@example.com'),
                        new OA\Property(property: 'subscription_plan', type: 'string', example: 'basic'),
                        new OA\Property(property: 'user_type', type: 'string', example: 'vendor'),
                        new OA\Property(
                            property: 'stores',
                            type: 'array',
                            description: 'Stores owned by this user',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Main Store'),
                                    new OA\Property(property: 'code', type: 'string', example: 'MAIN-001'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'assigned_stores',
                            type: 'array',
                            description: 'Stores where user is assigned as staff',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 2),
                                    new OA\Property(property: 'name', type: 'string', example: 'Branch Store'),
                                    new OA\Property(property: 'role', type: 'string', example: 'cashier'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $userData = $user->toArray();

        // Include owned stores
        $userData['stores'] = $user->ownedStores()
            ->select(['id', 'name', 'code', 'is_active'])
            ->get();

        // Include assigned stores (for staff members)
        $userData['assigned_stores'] = $user->assignedStores()
            ->select(['stores.id', 'stores.name', 'stores.code', 'store_user.role'])
            ->get()
            ->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'role' => $store->pivot->role,
                ];
            });

        return response()->json($userData);
    }
}
