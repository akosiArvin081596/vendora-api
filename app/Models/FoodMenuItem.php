<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
