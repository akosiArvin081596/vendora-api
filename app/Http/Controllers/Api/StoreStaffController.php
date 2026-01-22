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

class StoreStaffController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of staff members for a store.
     */
    public function index(Request $request, Store $store): AnonymousResourceCollection
    {
        $this->authorize('view', $store);

        $staff = $store->staff()->get();

        return StoreStaffResource::collection($staff);
    }

    /**
     * Add a staff member to a store.
     */
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

    /**
     * Update a staff member's role.
     */
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

    /**
     * Remove a staff member from a store.
     */
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

    /**
     * Get available roles.
     */
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
