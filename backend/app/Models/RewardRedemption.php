<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REWARD REDEMPTIONS: one row per points-for-reward exchange — see
 * migration comment for the `fulfilled` → `completed` rename and the
 * `failed` status this milestone adds.
 */
class RewardRedemption extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'shop_id', 'customer_id', 'reward_id', 'points_spent', 'balance_before', 'balance_after',
        'idempotency_key', 'status', 'shopify_price_rule_id', 'shopify_discount_id', 'shopify_discount_code',
        'failure_reason', 'metadata', 'fulfilled_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'fulfilled_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }
}
