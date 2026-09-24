<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REFERRALS: the state machine — see migration comment. `status` and
 * `fraud_status` are orthogonal (a referral can be `qualified` AND
 * `flagged` simultaneously — fraud review pauses reward payout without
 * being a lifecycle state of its own).
 */
class Referral extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    public const STATUS_CREATED = 'created';

    public const STATUS_CLICKED = 'clicked';

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REWARDED = 'rewarded';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FRAUD_DETECTED = 'fraud_detected';

    public const STATUS_CANCELLED = 'cancelled';

    // Legacy value from the original 4-state schema — treated as a
    // synonym of CANCELLED by anything reading historical rows.
    public const STATUS_EXPIRED = 'expired';

    public const FRAUD_STATUS_NONE = 'none';

    public const FRAUD_STATUS_FLAGGED = 'flagged';

    public const FRAUD_STATUS_CLEARED = 'cleared';

    public const FRAUD_STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'shop_id', 'referrer_customer_id', 'referred_customer_id',
        'referral_code', 'visitor_token', 'ip_address', 'user_agent',
        'status', 'fraud_status', 'fraud_reasons',
        'qualifying_order_id', 'qualifying_order_value_cents', 'rejection_reason',
        'clicked_at', 'registered_at', 'completed_at', 'qualified_at', 'rejected_at', 'cancelled_at',
        'attribution_expires_at', 'reward_scheduled_at', 'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'fraud_reasons' => 'array',
            'clicked_at' => 'datetime',
            'registered_at' => 'datetime',
            'completed_at' => 'datetime',
            'qualified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attribution_expires_at' => 'datetime',
            'reward_scheduled_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    public function isAttributionExpired(?\DateTimeInterface $at = null): bool
    {
        if (! $this->attribution_expires_at) {
            return false;
        }

        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        return $at->gt($this->attribution_expires_at);
    }

    public function isFlaggedForFraud(): bool
    {
        return in_array($this->fraud_status, [self::FRAUD_STATUS_FLAGGED, self::FRAUD_STATUS_CONFIRMED], true);
    }
}
