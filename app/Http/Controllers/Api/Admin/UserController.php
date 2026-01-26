<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Users', description: 'Admin user management endpoints')]
class UserController extends Controller
{
    #[OA\Get(
        path: '/api/admin/users',
        tags: ['Admin - Users'],
        summary: 'List all users (Admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Search by name or email', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_type', in: 'query', description: 'Filter by user type', schema: new OA\Schema(type: 'string', enum: ['admin', 'vendor', 'manager', 'cashier', 'buyer'])),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'suspended'])),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of users'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = User::query();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // User type filter
        if ($userType = $request->input('user_type')) {
            $query->where('user_type', $userType);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->input('per_page', 20);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($users);
    }

    #[OA\Post(
        path: '/api/admin/users',
        tags: ['Admin - Users'],
        summary: 'Create a new user (Admin only)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'user_type'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'user_type', type: 'string', enum: ['admin', 'vendor', 'manager', 'cashier', 'buyer'], example: 'cashier'),
                    new OA\Property(property: 'phone', type: 'string', example: '+639123456789'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'suspended'], example: 'active'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user = User::query()->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'user_type' => $request->user_type,
            'phone' => $request->phone,
            'status' => $request->status ?? UserStatus::Active->value,
        ]);

        AuditLog::log('user_created', $user, [
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/users/{id}',
        tags: ['Admin - Users'],
        summary: 'Get a specific user (Admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User details'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json($user);
    }

    #[OA\Put(
        path: '/api/admin/users/{id}',
        tags: ['Admin - Users'],
        summary: 'Update a user (Admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword123'),
                    new OA\Property(property: 'user_type', type: 'string', enum: ['admin', 'vendor', 'manager', 'cashier', 'buyer']),
                    new OA\Property(property: 'phone', type: 'string', example: '+639123456789'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'suspended']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $this->authorizeManageUser($request, $user);

        $data = $request->only(['name', 'email', 'user_type', 'phone', 'status']);

        // Only update password if provided
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        AuditLog::log('user_updated', $user, [
            'updated_by' => $request->user()->id,
            'changes' => array_keys($data),
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/users/{id}',
        tags: ['Admin - Users'],
        summary: 'Delete a user (Admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $this->authorizeManageUser($request, $user);

        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        AuditLog::log('user_deleted', $user, [
            'deleted_by' => $request->user()->id,
            'user_email' => $user->email,
        ]);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/users/{id}/status',
        tags: ['Admin - Users'],
        summary: 'Change user status (Admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'suspended'], example: 'suspended'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $this->authorizeManageUser($request, $user);

        $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
        ]);

        // Prevent self-deactivation
        if ($request->user()->id === $user->id && $request->status !== 'active') {
            return response()->json([
                'message' => 'You cannot deactivate your own account',
            ], 403);
        }

        $oldStatus = $user->status;
        $user->update(['status' => $request->status]);

        AuditLog::log('user_status_changed', $user, [
            'changed_by' => $request->user()->id,
            'old_status' => $oldStatus,
            'new_status' => $request->status,
        ]);

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Authorize that the current user is an admin.
     */
    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    /**
     * Authorize that the current user can manage the target user.
     */
    private function authorizeManageUser(Request $request, User $targetUser): void
    {
        $currentUser = $request->user();

        if (! $currentUser->user_type->canManage($targetUser->user_type)) {
            abort(403, 'You do not have permission to manage this user');
        }
    }
}
