<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreProduct extends Model
{
    /** @use HasFactory<\Database\Factories\StoreProductFactory> */
    use HasFactory;

    use SerializesDatesInAppTimezone;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'product_id',
        'stock',
        'min_stock',
        'max_stock',
        'price_override',
        'is_available',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'price_override' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    /**
     * Get the store.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the effective price (override or master product price).
     */
    protected function effectivePrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price_override ?? $this->product->price,
        );
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(): bool
    {
        if ($this->min_stock === null) {
            return false;
        }

        return $this->stock <= $this->min_stock;
    }

    /**
     * Check if out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }
}
