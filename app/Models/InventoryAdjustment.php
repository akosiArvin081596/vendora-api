<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryAdjustmentFactory> */
    use HasFactory;

    use SerializesDatesInAppTimezone;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'product_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_cost',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'unit_cost' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the store where this adjustment was made.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function costLayer(): HasOne
    {
        return $this->hasOne(InventoryCostLayer::class);
    }
}
