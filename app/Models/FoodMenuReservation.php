<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FoodMenuReservation',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'food_menu_item_id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'customer_id', type: 'integer', example: 5, nullable: true),
        new OA\Property(property: 'customer_name', type: 'string', example: 'Juan Dela Cruz'),
        new OA\Property(property: 'customer_phone', type: 'string', example: '09171234567', nullable: true),
        new OA\Property(property: 'servings', type: 'integer', example: 3),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'cancelled', 'completed'], example: 'pending'),
        new OA\Property(property: 'notes', type: 'string', example: 'Extra rice', nullable: true),
        new OA\Property(property: 'reserved_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'food_menu_item', ref: '#/components/schemas/FoodMenuItem', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
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
