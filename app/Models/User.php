<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use SerializesDatesInAppTimezone;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'status',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserType::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the vendor profile for this user.
     */
    public function vendorProfile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->user_type === UserType::Admin;
    }

    /**
     * Check if the user is a vendor.
     */
    public function isVendor(): bool
    {
        return $this->user_type === UserType::Vendor;
    }

    /**
     * Check if the user is a buyer.
     */
    public function isBuyer(): bool
    {
        return $this->user_type === UserType::Buyer;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get stores owned by this user.
     */
    public function ownedStores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Get stores this user is assigned to as staff.
     */
    public function assignedStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot(['role', 'permissions', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Get all stores this user has access to.
     *
     * @return Collection<int, Store>
     */
    public function allAccessibleStores(): Collection
    {
        return $this->ownedStores->merge($this->assignedStores);
    }
}
