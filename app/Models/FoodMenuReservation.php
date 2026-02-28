<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodMenuReservation extends Model
{
    use HasFactory;
    use SerializesDatesInAppTimezone;

    protected $fillable = [
        'food_menu_item_id',
        'user_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'servings',
        'status',
        'notes',
        'reserved_at',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'integer',
            'status' => ReservationStatus::class,
            'reserved_at' => 'datetime',
        ];
    }

    public function foodMenuItem(): BelongsTo
    {
        return $this->belongsTo(FoodMenuItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
