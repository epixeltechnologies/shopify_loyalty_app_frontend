<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BILLING: a shop's subscription state at a point in time. A plan change
 * inserts a new row rather than updating `plan_id` in place, so
 * subscription history is queryable (see migration comment).
 */
class Subscription extends Model
{
    use BelongsToShop, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const ACTIVE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_TRIALING];

    protected $fillable = [
        'shop_id', 'plan_id', 'shopify_subscription_id', 'status',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancelled_at', 'shopify_payload',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'shopify_payload' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
