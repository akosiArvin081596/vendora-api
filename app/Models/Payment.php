<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable;

    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    use SerializesDatesInAppTimezone;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'order_id',
        'payment_number',
        'paid_at',
        'amount',
        'currency',
        'method',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the store where this payment was processed.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
