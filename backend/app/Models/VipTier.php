<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * VIP: shop-defined tiers (Silver/Gold/Platinum by default) — see
 * migration comment. Which tier *slugs* a shop may create is gated by
 * the `vip_tier.*` features on its plan (EntitlementService), not by
 * anything in this table itself — see docs/VIP_TIERS.md for why this
 * already implements the task's "max tier count" plan restriction
 * without a separate numeric limit.
 *
 * `perks` remains a free-form JSON bag deliberately — see
 * VipBenefitService's docblock for why benefit types are never
 * enumerated in code.
 */
class VipTier extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    public const METHOD_POINTS_EARNED = 'points_earned';

    public const METHOD_TOTAL_SPEND = 'total_spend';

    public const METHOD_ORDER_COUNT = 'order_count';

    public const PERIOD_LIFETIME = 'lifetime';

    public const PERIOD_CALENDAR_YEAR = 'calendar_year';

    public const PERIOD_ROLLING = 'rolling';

    protected $fillable = [
        'shop_id', 'name', 'description', 'slug', 'qualification_method',
        'threshold_points', 'minimum_spend_cents', 'minimum_orders',
        'evaluation_period', 'rolling_period_days', 'starts_at', 'ends_at',
        'sort_order', 'perks', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'perks' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** The numeric threshold actually relevant for this tier's chosen qualification_method — the one VipQualificationService compares a customer's computed metric against. */
    public function threshold(): int
    {
        return match ($this->qualification_method) {
            self::METHOD_TOTAL_SPEND => $this->minimum_spend_cents ?? 0,
            self::METHOD_ORDER_COUNT => $this->minimum_orders ?? 0,
            default => $this->threshold_points ?? 0,
        };
    }

    public function isWithinDateWindow(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        if ($this->starts_at && $at->lt($this->starts_at)) {
            return false;
        }

        return ! ($this->ends_at && $at->gt($this->ends_at));
    }

    /**
     * Whether this shop's CURRENT plan still entitles it to this tier's
     * slug — false after a Professional→Starter plan downgrade for a
     * pre-existing Platinum tier, without the tier row (or any
     * customer's history/assignment) ever being deleted. See
     * docs/VIP_TIERS.md's plan-downgrade section.
     */
    public function isAvailableToShop(): bool
    {
        return $this->shop->entitlements()->has("vip_tier.{$this->slug}");
    }
}
