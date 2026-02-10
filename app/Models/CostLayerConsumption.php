<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostLayerConsumption extends Model
{
    /** @use HasFactory<\Database\Factories\CostLayerConsumptionFactory> */
    use HasFactory;

    use SerializesDatesInAppTimezone;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cost_layer_id',
        'order_item_id',
        'inventory_adjustment_id',
        'quantity_consumed',
        'unit_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_consumed' => 'integer',
            'unit_cost' => 'integer',
        ];
    }

    public function costLayer(): BelongsTo
    {
        return $this->belongsTo(InventoryCostLayer::class, 'cost_layer_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }
}
