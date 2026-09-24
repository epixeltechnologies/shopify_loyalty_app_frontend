<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CUSTOMERS: a loyalty program member. Point balances deliberately live
 * on the related `Point` model, not here — see the `points` migration's
 * comment for why they're split.
 */
class Customer extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id', 'shopify_customer_id', 'email', 'first_name', 'last_name',
        'birthday_month', 'birthday_day',
        'vip_tier_id', 'vip_tier_evaluated_at', 'referral_code',
        'referred_by_customer_id', 'status', 'enrolled_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'vip_tier_evaluated_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function vipTier(): BelongsTo
    {
        return $this->belongsTo(VipTier::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_customer_id');
    }

    public function point(): HasOne
    {
        return $this->hasOne(Point::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function rewardRedemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_customer_id');
    }

    public function vipHistory(): HasMany
    {
        return $this->hasMany(CustomerVipHistory::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Convenience accessor so callers can do $customer->points_balance without an extra join everywhere. */
    public function getPointsBalanceAttribute(): int
    {
        return $this->point?->balance ?? 0;
    }
}
