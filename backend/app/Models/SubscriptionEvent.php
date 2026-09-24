<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** BILLING: append-only subscription state-transition log — see migration comment. */
class SubscriptionEvent extends Model
{
    use BelongsToShop, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'shop_id', 'subscription_id', 'from_status', 'to_status',
        'from_plan_id', 'to_plan_id', 'trigger', 'shopify_payload',
    ];

    protected function casts(): array
    {
        return ['shopify_payload' => 'array', 'occurred_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }
}
