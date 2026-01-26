<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable;

    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    use SerializesDatesInAppTimezone;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'description',
        'sku',
        'barcode',
        'price',
        'cost',
        'currency',
        'unit',
        'stock',
        'min_stock',
        'max_stock',
        'image',
        'is_active',
        'is_ecommerce',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'is_active' => 'boolean',
            'is_ecommerce' => 'boolean',
        ];
    }

    public function hasBarcode(): bool
    {
        return ! empty($this->barcode);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the store products (inventory per store).
     */
    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    /**
     * Get the stores that have this product.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_products')
            ->withPivot(['stock', 'min_stock', 'max_stock', 'price_override', 'is_available'])
            ->withTimestamps();
    }

    /**
     * Get the stock at a specific store.
     */
    public function stockAtStore(Store $store): int
    {
        return $this->storeProducts()
            ->where('store_id', $store->id)
            ->value('stock') ?? 0;
    }

    /**
     * Get the bulk prices for this product.
     */
    public function bulkPrices(): HasMany
    {
        return $this->hasMany(ProductBulkPrice::class)->orderBy('min_qty');
    }
}
