<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FoodMenuItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'store_id', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'name', type: 'string', example: 'Chicken Adobo'),
        new OA\Property(property: 'description', type: 'string', example: 'Classic Filipino chicken adobo', nullable: true),
        new OA\Property(property: 'category', type: 'string', example: 'Main Course', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 120.00, nullable: true),
        new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
        new OA\Property(property: 'image', type: 'string', example: 'http://vendora.test/storage/food-menu/img.jpg', nullable: true),
        new OA\Property(property: 'total_servings', type: 'integer', example: 50),
        new OA\Property(property: 'reserved_servings', type: 'integer', example: 10),
        new OA\Property(property: 'remaining_servings', type: 'integer', example: 40),
        new OA\Property(property: 'is_sold_out', type: 'boolean', example: false),
        new OA\Property(property: 'is_available', type: 'boolean', example: true),
        new OA\Property(property: 'reservations', type: 'array', items: new OA\Items(ref: '#/components/schemas/FoodMenuReservation')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class FoodMenuItem extends Model
{
    use Auditable;
    use HasFactory;
    use SerializesDatesInAppTimezone;

    protected $fillable = [
        'user_id',
        'store_id',
        'name',
        'description',
        'category',
        'price',
        'currency',
        'image',
        'total_servings',
        'reserved_servings',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'total_servings' => 'integer',
            'reserved_servings' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(FoodMenuReservation::class);
    }

    public function remainingServings(): int
    {
        return max(0, $this->total_servings - $this->reserved_servings);
    }

    public function isSoldOut(): bool
    {
        return $this->remainingServings() <= 0;
    }
}
