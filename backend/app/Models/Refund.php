<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REFUNDS: one row per Shopify refund event — see migration comment for the points-reversal-readiness fields. */
class Refund extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'shop_id', 'order_id', 'shopify_refund_id', 'amount_cents', 'currency',
        'is_partial', 'note', 'shopify_created_at', 'points_reversed_at', 'point_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'is_partial' => 'boolean',
            'shopify_created_at' => 'datetime',
            'points_reversed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function needsPointsReversal(): bool
    {
        return $this->points_reversed_at === null;
    }
}
