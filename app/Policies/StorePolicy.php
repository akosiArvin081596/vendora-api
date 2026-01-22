<?php

namespace App\Policies;

use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Store $store): bool
    {
        return $this->hasAccess($user, $store);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Store $store): bool
    {
        return $this->isOwnerOrManager($user, $store);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Store $store): bool
    {
        return $store->user_id === $user->id;
    }

    /**
     * Determine whether the user can manage staff for this store.
     */
    public function manageStaff(User $user, Store $store): bool
    {
        return $this->isOwnerOrManager($user, $store);
    }

    /**
     * Determine whether the user can manage products for this store.
     */
    public function manageProducts(User $user, Store $store): bool
    {
        return $this->hasPermission($user, $store, 'products.update');
    }

    /**
     * Determine whether the user can view products for this store.
     */
    public function viewProducts(User $user, Store $store): bool
    {
        return $this->hasPermission($user, $store, 'products.view');
    }

    /**
     * Determine whether the user can create orders for this store.
     */
    public function createOrders(User $user, Store $store): bool
    {
        return $this->hasPermission($user, $store, 'orders.create');
    }

    /**
     * Determine whether the user can adjust inventory for this store.
     */
    public function adjustInventory(User $user, Store $store): bool
    {
        return $this->hasPermission($user, $store, 'inventory.adjust');
    }

    /**
     * Check if user has access to the store.
     */
    protected function hasAccess(User $user, Store $store): bool
    {
        return $store->user_id === $user->id ||
            $store->staff()->where('users.id', $user->id)->exists();
    }

    /**
     * Check if user is owner or manager.
     */
    protected function isOwnerOrManager(User $user, Store $store): bool
    {
        if ($store->user_id === $user->id) {
            return true;
        }

        $pivot = $store->staff()->where('users.id', $user->id)->first()?->pivot;

        return $pivot && in_array($pivot->role, [StoreRole::Manager->value], true);
    }

    /**
     * Check if user has a specific permission at this store.
     */
    protected function hasPermission(User $user, Store $store, string $permission): bool
    {
        if ($store->user_id === $user->id) {
            return true;
        }

        $pivot = $store->staff()->where('users.id', $user->id)->first()?->pivot;

        if (! $pivot) {
            return false;
        }

        $role = StoreRole::tryFrom($pivot->role);

        if (! $role) {
            return false;
        }

        return $role->hasPermission($permission);
    }
}
