<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** BILLING: denormalized usage counters — see UsageTrackerService. */
class SubscriptionUsage extends Model
{
    use BelongsToShop, HasFactory;

    protected $table = 'subscription_usage';

    public const METRIC_ACTIVE_CUSTOMERS = 'active_customers';

    public const METRIC_ACTIVE_POINT_RULES = 'active_point_rules';

    public const METRIC_ACTIVE_REWARDS = 'active_rewards';

    protected $fillable = ['shop_id', 'metric', 'value', 'recalculated_at'];

    protected function casts(): array
    {
        return ['recalculated_at' => 'datetime'];
    }
}
