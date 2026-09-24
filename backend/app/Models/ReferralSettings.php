<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** REFERRALS: 1:1 with Shop — every merchant-configurable referral-program setting. See migration comment. */
class ReferralSettings extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    protected $table = 'referral_settings';

    protected $fillable = [
        'shop_id', 'enabled', 'referrer_reward_points', 'referee_reward_points',
        'minimum_qualifying_order_cents', 'require_first_purchase',
        'reward_delay_days', 'attribution_window_days', 'max_referrals_per_customer',
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'require_first_purchase' => 'boolean'];
    }
}
