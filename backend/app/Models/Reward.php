<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REWARDS: the redeemable catalog — see migration comment. `type` is a
 * plain string (not a DB enum — see that migration's rationale) so a
 * future reward type is purely an application-level addition: a new
 * `RewardDiscountTypeInterface` implementation registered in
 * `AppServiceProvider`, never a schema change. See docs/REWARDS_ENGINE.md.
 */
class Reward extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    public const TYPE_FIXED_DISCOUNT = 'fixed_discount';

    public const TYPE_PERCENTAGE_DISCOUNT = 'percentage_discount';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    // Pre-existing value, kept for backward compatibility — no handler
    // implements this yet (not one of this task's three required
    // types); creating a 'gift' reward will fail fulfillment cleanly
    // with a clear "unsupported reward type" error rather than
    // pretending to work. See ShopifyDiscountService.
    public const TYPE_GIFT = 'gift';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'shop_id', 'name', 'description', 'image_url', 'type', 'points_cost', 'value',
        'min_purchase_amount_cents', 'max_discount_amount_cents',
        'included_product_ids', 'excluded_product_ids', 'included_collection_ids', 'excluded_collection_ids',
        'customer_eligibility', 'max_total_redemptions', 'max_redemptions_per_customer',
        'starts_at', 'ends_at', 'stock_limit', 'status',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'included_product_ids' => 'array',
            'excluded_product_ids' => 'array',
            'included_collection_ids' => 'array',
            'excluded_collection_ids' => 'array',
            'customer_eligibility' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    /** Non-terminal redemptions — the ones that count against usage limits (a failed/cancelled attempt never consumed a slot). */
    public function activeRedemptions(): HasMany
    {
        return $this->redemptions()->whereIn('status', [RewardRedemption::STATUS_PENDING, RewardRedemption::STATUS_COMPLETED]);
    }

    public function isWithinDateWindow(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        if ($this->starts_at && $at->lt($this->starts_at)) {
            return false;
        }

        return ! ($this->ends_at && $at->gt($this->ends_at));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
