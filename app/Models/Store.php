<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use Auditable;

    /** @use HasFactory<\Database\Factories\StoreFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'is_active',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Get the owner (vendor) of this store.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the staff members assigned to this store.
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot(['role', 'permissions', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Get the products available at this store.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'store_products')
            ->withPivot(['stock', 'min_stock', 'max_stock', 'price_override', 'is_available'])
            ->withTimestamps();
    }

    /**
     * Get the store product records.
     */
    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    /**
     * Get the orders placed at this store.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the customers of this store.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get the payments processed at this store.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the inventory adjustments for this store.
     */
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    /**
     * Check if a user has access to this store.
     */
    public function isAccessibleBy(User $user): bool
    {
        return $this->user_id === $user->id ||
            $this->staff()->where('users.id', $user->id)->exists();
    }

    /**
     * Get the user's role at this store.
     */
    public function getUserRole(User $user): ?string
    {
        if ($this->user_id === $user->id) {
            return 'owner';
        }

        return $this->staff()
            ->where('users.id', $user->id)
            ->first()
            ?->pivot
            ?->role;
    }
}
