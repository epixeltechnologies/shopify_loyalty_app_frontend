<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\SubscriptionUsage;

/**
 * BILLING: answers "how much of this shop's plan has it used, and can it
 * do one more" — split out from EntitlementService (which now delegates
 * to this class for every limit-related method, keeping
 * `$shop->entitlements()->canAddCustomer()` etc. working unchanged for
 * every existing caller) so usage/limit logic has one focused home,
 * separate from boolean feature-flag checks
 * (`FeatureEntitlementService`/`EntitlementService::has()`).
 *
 * Reads limits from `plans` (via the resolved Plan, never hard-coded)
 * and current usage from `UsageTrackerService`'s denormalized counters
 * — never a live `COUNT(*)`, so limit checks stay cheap even for a
 * shop with hundreds of thousands of customers. See
 * docs/BILLING.md#usage-limits.
 */
class UsageLimitService
{
    public function __construct(private readonly UsageTrackerService $usage) {}

    /** Numeric plan limit for a given plan column. Null = unlimited. */
    public function limitFor(?Plan $plan, string $column): ?int
    {
        if (! $plan) {
            return 0; // no plan => no allowance
        }

        $value = $plan->{$column};

        return $value === null ? null : (int) $value;
    }

    public function canAddCustomer(Shop $shop, ?Plan $plan): bool
    {
        $limit = $this->limitFor($plan, 'max_active_customers');
        if ($limit === null) {
            return true;
        }

        return $this->usage->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS) < $limit;
    }

    public function canAddPointRule(Shop $shop, ?Plan $plan): bool
    {
        $limit = $this->limitFor($plan, 'max_active_point_rules');
        if ($limit === null) {
            return true;
        }

        return $this->usage->current($shop, SubscriptionUsage::METRIC_ACTIVE_POINT_RULES) < $limit;
    }

    /** The Reward-catalog counterpart — see `max_active_rewards` migration's note on this being a distinct limit from `max_active_point_rules`. */
    public function canAddReward(Shop $shop, ?Plan $plan): bool
    {
        $limit = $this->limitFor($plan, 'max_active_rewards');
        if ($limit === null) {
            return true;
        }

        return $this->usage->current($shop, SubscriptionUsage::METRIC_ACTIVE_REWARDS) < $limit;
    }

    /** Generic form of canAddCustomer()/canAddPointRule() — the inverse framing the task's "hasReachedLimit()" name asks for. */
    public function hasReachedLimit(Shop $shop, ?Plan $plan, string $limitColumn, string $usageMetric): bool
    {
        $limit = $this->limitFor($plan, $limitColumn);
        if ($limit === null) {
            return false;
        }

        return $this->usage->current($shop, $usageMetric) >= $limit;
    }

    /**
     * Atomically checks-and-reserves one customer slot — see
     * UsageTrackerService::tryIncrementIfUnderLimit()'s docblock for the
     * race condition this closes. Callers must call this INSTEAD of
     * `canAddCustomer()` immediately followed by a separate increment;
     * the two together are the vulnerable pattern.
     */
    public function tryReserveCustomerSlot(Shop $shop, ?Plan $plan): bool
    {
        return $this->usage->tryIncrementIfUnderLimit($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, $this->limitFor($plan, 'max_active_customers'));
    }

    public function tryReservePointRuleSlot(Shop $shop, ?Plan $plan): bool
    {
        return $this->usage->tryIncrementIfUnderLimit($shop, SubscriptionUsage::METRIC_ACTIVE_POINT_RULES, $this->limitFor($plan, 'max_active_point_rules'));
    }

    public function tryReserveRewardSlot(Shop $shop, ?Plan $plan): bool
    {
        return $this->usage->tryIncrementIfUnderLimit($shop, SubscriptionUsage::METRIC_ACTIVE_REWARDS, $this->limitFor($plan, 'max_active_rewards'));
    }

    public function remaining(Shop $shop, ?Plan $plan, string $limitColumn, string $usageMetric): ?int
    {
        $limit = $this->limitFor($plan, $limitColumn);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->usage->current($shop, $usageMetric));
    }

    public function remainingCustomerSlots(Shop $shop, ?Plan $plan): ?int
    {
        return $this->remaining($shop, $plan, 'max_active_customers', SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS);
    }

    public function remainingPointRuleSlots(Shop $shop, ?Plan $plan): ?int
    {
        return $this->remaining($shop, $plan, 'max_active_point_rules', SubscriptionUsage::METRIC_ACTIVE_POINT_RULES);
    }

    public function remainingRewardSlots(Shop $shop, ?Plan $plan): ?int
    {
        return $this->remaining($shop, $plan, 'max_active_rewards', SubscriptionUsage::METRIC_ACTIVE_REWARDS);
    }

    /**
     * Current usage as a fraction of the limit (0.0–1.0), or null when
     * unlimited — the number the frontend's usage bars
     * (docs/BILLING.md's Billing UI) render directly.
     */
    public function usageRatio(Shop $shop, ?Plan $plan, string $limitColumn, string $usageMetric): ?float
    {
        $limit = $this->limitFor($plan, $limitColumn);
        if ($limit === null || $limit === 0) {
            return $limit === 0 ? 1.0 : null;
        }

        return min(1.0, $this->usage->current($shop, $usageMetric) / $limit);
    }
}
