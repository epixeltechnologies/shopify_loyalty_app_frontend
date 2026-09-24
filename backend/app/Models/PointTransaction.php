<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * POINT SYSTEM: immutable ledger row — see migration comment. Only
 * `created_at` is timestamped (`UPDATED_AT = null`); a ledger row is
 * never updated once written — a correction is always a new
 * compensating row (see PointReversalService / PointAdjustmentService),
 * never an edit to history.
 *
 * `direction` (earn/redeem/expire/adjust) is the balance-math sign —
 * whether this row increases or decreases the balance and which
 * lifetime counter it feeds. `source` is the specific business reason
 * (see the SOURCE_* constants) — the classification the task/product
 * vocabulary calls a "transaction type." The two are deliberately
 * distinct: a `birthday_reward` and a `referral_reward` are both an
 * `earn` for balance-math purposes, but very different things from a
 * reporting/audit perspective, which `source` is what answers.
 */
class PointTransaction extends Model
{
    use BelongsToShop, HasFactory;

    public const UPDATED_AT = null;

    public const DIRECTION_EARN = 'earn';

    public const DIRECTION_REDEEM = 'redeem';

    public const DIRECTION_EXPIRE = 'expire';

    public const DIRECTION_ADJUST = 'adjust';

    // "source" values — the task's transaction-type vocabulary.
    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_REWARD_REDEMPTION = 'reward_redemption';

    public const SOURCE_EXPIRATION = 'expiration';

    public const SOURCE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const SOURCE_ADMINISTRATIVE_CORRECTION = 'administrative_correction';

    public const SOURCE_REFUND_REVERSAL = 'refund_reversal';

    public const SOURCE_CANCELLATION_REVERSAL = 'cancellation_reversal';

    public const SOURCE_REFERRAL_REWARD = 'referral_reward';

    /** The referred customer's ("referee's") welcome bonus for signing up via a referral — distinct from SOURCE_REFERRAL_REWARD, which is the referrer's payout for a successful referral. See docs/REFERRAL_PROGRAM.md. */
    public const SOURCE_REFERRAL_BONUS = 'referral_bonus';

    public const SOURCE_BIRTHDAY_REWARD = 'birthday_reward';

    public const SOURCE_ACCOUNT_CREATION_REWARD = 'account_creation_reward';

    public const SOURCE_REVIEW_REWARD = 'review_reward';

    /** A system-initiated credit reversing a redemption whose Shopify discount could not be created — see FulfillRewardRedemptionJob. Distinct from SOURCE_REFUND_REVERSAL (a Shopify order refund) and SOURCE_ADMINISTRATIVE_CORRECTION (a manual data-fix). */
    public const SOURCE_REDEMPTION_REFUND = 'redemption_refund';

    protected $fillable = [
        'shop_id', 'customer_id', 'point_rule_id', 'direction', 'points', 'balance_after',
        'source', 'source_reference_type', 'source_reference_id', 'note', 'metadata',
        'idempotency_key', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'metadata' => 'array'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pointRule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class);
    }

    /** Polymorphic link to whatever produced this ledger entry (RewardRedemption, Referral, ...). */
    public function sourceReference(): MorphTo
    {
        return $this->morphTo();
    }
}
