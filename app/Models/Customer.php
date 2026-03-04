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
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'status',
        'orders_count',
        'total_spent',
        'credit_balance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orders_count' => 'integer',
            'total_spent' => 'integer',
            'credit_balance' => 'integer',
        ];
    }

    /**
     * Compose a full name from first/middle/last name parts.
     *
     * @param  array{first_name: string, middle_name?: string|null, last_name: string}  $parts
     */
    public static function composeName(array $parts): string
    {
        return collect([
            $parts['first_name'] ?? null,
            $parts['middle_name'] ?? null,
            $parts['last_name'] ?? null,
        ])->filter()->implode(' ');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Get the store this customer belongs to.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
