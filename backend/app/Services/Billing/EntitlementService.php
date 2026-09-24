<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Shop;
use App\Services\Cache\CacheService;
use App\Support\Cache\CacheKeys;

/**
 * Single source of truth for "can this shop do X" — feature flags AND
 * (by delegation to UsageLimitService, below) usage limits. Reads plan
 * limits and feature flags entirely from the database (plans, features,
 * plan_features) — nothing here is hard-coded to "Starter" or
 * "Professional" by name, so adding a third plan or a new feature never
 * requires a code change. See docs/ENTITLEMENTS.md.
 *
 * This class is what the task/architecture docs refer to as the
 * "FeatureEntitlementService" — see App\Services\Billing\FeatureEntitlementService,
 * a same-behavior alias kept for that exact name, since renaming this
 * class outright would touch every existing `$shop->entitlements()`
 * call site (Shop model, EnsureFeatureEntitlement middleware,
 * RequireActiveSubscription, CustomerService, PointRuleService) for no
 * behavioral gain.
 *
 * Usage:
 *   $shop->entitlements()->canAddCustomer();
 *   $shop->entitlements()->has('analytics.advanced');
 *   $shop->entitlements()->limit('max_active_customers');
 */
class EntitlementService
{
    private ?Shop $shop = null;

    public function __construct(
        private readonly UsageLimitService $usageLimits,
        private readonly CacheService $cache,
    ) {}

    public function forShop(Shop $shop): self
    {
        $clone = clone $this;
        $clone->shop = $shop;

        return $clone;
    }

    public function plan(): ?Plan
    {
        return $this->cache->remember(
            CacheKeys::shopActivePlan($this->shop->id),
            CacheKeys::TTL_SHORT,
            fn () => $this->shop->activeSubscription?->plan,
            shopId: $this->shop->id,
        );
    }

    /** No active (paid/trialing) subscription => hard-blocked. Enforced app-wide. */
    public function hasActiveSubscription(): bool
    {
        return $this->plan() !== null;
    }

    /** Boolean or string feature flag lookup, e.g. 'vip_tier.platinum', 'export.csv'. */
    public function has(string $featureKey): bool
    {
        $plan = $this->plan();
        if (! $plan) {
            return false;
        }

        return $this->cache->remember(
            CacheKeys::shopFeature($this->shop->id, $featureKey),
            CacheKeys::TTL_SHORT,
            function () use ($plan, $featureKey) {
                $value = $plan->features()->where('key', $featureKey)->first()?->pivot->value;

                return $value !== null && $value !== '0';
            },
            shopId: $this->shop->id,
        );
    }

    /** Alias of has() under the name docs/ENTITLEMENTS.md's method reference uses — identical behavior. */
    public function hasFeature(string $featureKey): bool
    {
        return $this->has($featureKey);
    }

    /**
     * Same check as has()/hasFeature() today (an active subscription is
     * already required for `plan()` to return non-null, so there's
     * nothing further to add) — kept as a distinct, explicitly-named
     * method because "can I use this" is the one callers/controllers
     * should reach for, while "has()" reads more like a raw flag lookup
     * a lower-level caller (a cache key builder, a test assertion)
     * would use. If a future requirement adds a reason a feature could
     * be flagged-on-the-plan-but-still-unusable (e.g. a paused add-on),
     * this is the one place that logic would go.
     */
    public function canUseFeature(string $featureKey): bool
    {
        return $this->has($featureKey);
    }

    public function featureValue(string $featureKey): ?string
    {
        $plan = $this->plan();
        if (! $plan) {
            return null;
        }

        return $plan->features()->where('key', $featureKey)->first()?->pivot->value;
    }

    /** Numeric plan limit, e.g. 'max_active_customers'. Returns null for unlimited. */
    public function limit(string $column): ?int
    {
        return $this->usageLimits->limitFor($this->plan(), $column);
    }

    /** Alias of limit() under the name docs/ENTITLEMENTS.md's method reference uses. */
    public function getLimit(string $column): ?int
    {
        return $this->limit($column);
    }

    /** Generic "is this shop at its limit for X" — the inverse of canAddCustomer()/canAddPointRule() for an arbitrary metric pair. */
    public function hasReachedLimit(string $limitColumn, string $usageMetric): bool
    {
        return $this->usageLimits->hasReachedLimit($this->shop, $this->plan(), $limitColumn, $usageMetric);
    }

    public function canAddCustomer(): bool
    {
        return $this->usageLimits->canAddCustomer($this->shop, $this->plan());
    }

    /**
     * Alias for canAddPointRule() under the product/task terminology
     * ("reward campaigns") — the underlying schema/domain naming is
     * `point_rules` (see docs/DATABASE.md for why: a point rule is how
     * points are earned, which is the mechanism a "reward campaign" is
     * built from). Both names resolve to the exact same check; this
     * exists so code written against the task's vocabulary reads
     * naturally without a second, parallel implementation.
     */
    public function canCreateRewardCampaign(): bool
    {
        return $this->canAddPointRule();
    }

    public function canAddPointRule(): bool
    {
        return $this->usageLimits->canAddPointRule($this->shop, $this->plan());
    }

    /**
     * The Reward-catalog limit ("reward campaigns" per this task's
     * vocabulary) — distinct from `canCreateRewardCampaign()` above,
     * which is an older alias for the point-rule limit. See
     * `max_active_rewards` migration's note.
     */
    public function canAddReward(): bool
    {
        return $this->usageLimits->canAddReward($this->shop, $this->plan());
    }

    /**
     * Generic dispatcher over the resource-specific canAdd*() methods —
     * useful for a single generic policy/middleware/console command
     * that receives a resource type as a string and shouldn't need to
     * know which specific method to call for it.
     */
    public function canCreate(string $resource): bool
    {
        return match ($resource) {
            'customer' => $this->canAddCustomer(),
            'point_rule', 'reward_campaign' => $this->canAddPointRule(),
            'reward' => $this->canAddReward(),
            default => throw new \InvalidArgumentException("Unknown entitlement resource [{$resource}]."),
        };
    }

    public function canUseVipTier(string $slug): bool
    {
        return $this->has("vip_tier.{$slug}");
    }

    public function canExportCsv(): bool
    {
        return $this->has('export.csv');
    }

    public function canUseApi(): bool
    {
        return $this->has('api.full_access');
    }

    public function remainingCustomerSlots(): ?int
    {
        return $this->usageLimits->remainingCustomerSlots($this->shop, $this->plan());
    }

    public function remainingPointRuleSlots(): ?int
    {
        return $this->usageLimits->remainingPointRuleSlots($this->shop, $this->plan());
    }

    public function availableVipTiers(): array
    {
        $plan = $this->plan();
        if (! $plan) {
            return [];
        }

        return $plan->features()
            ->where('group', 'vip_tiers')
            ->wherePivot('value', '1')
            ->pluck('key')
            ->map(fn (string $key) => str($key)->after('vip_tier.')->toString())
            ->all();
    }

    /** @deprecated Use CacheService::forgetShop() directly — kept so existing call sites (SyncPlanEntitlementsCache) don't break. */
    public function forgetCache(): void
    {
        $this->cache->forgetShop($this->shop->id);
    }
}
