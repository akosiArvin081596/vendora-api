<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    use SerializesDatesInAppTimezone;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'name',
        'email',
        'phone',
        'status',
        'orders_count',
        'total_spent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orders_count' => 'integer',
            'total_spent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the store this customer belongs to.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
