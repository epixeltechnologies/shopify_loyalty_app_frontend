<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * POINT SYSTEM: the current denormalized point state for one customer
 * (1:1). `balance` is a cache derived from `point_transactions` — see
 * that migration's comment and PointsLedgerService, the only writer.
 */
class Point extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'shop_id', 'customer_id', 'balance', 'lifetime_earned',
        'lifetime_redeemed', 'lifetime_expired', 'lifetime_adjusted',
        'pending_expiry_amount', 'next_expiry_date', 'last_transaction_at',
    ];

    protected function casts(): array
    {
        return ['next_expiry_date' => 'date', 'last_transaction_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
