<?php

namespace App\Services\Billing;

use App\DTOs\DowngradeEligibility;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\SubscriptionUsage;
use App\Models\VipTier;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * BILLING: the plan catalog, plus the one piece of genuinely new
 * business logic this milestone adds — safe-downgrade checking. See
 * docs/BILLING.md#downgrade-safety.
 *
 * Deliberately does not hard-code which plan is "smaller" by name —
 * "is this a downgrade" is inferred from `sort_order` (already the
 * column `plans` is ordered by for display), so a future third plan
 * slotted in at any position is compared correctly without a code change.
 */
class PlanService
{
    public function __construct(
        private readonly PlanRepositoryInterface $plans,
        private readonly UsageLimitService $usageLimits,
        private readonly UsageTrackerService $usage,
    ) {}

    public function all(): Collection
    {
        return $this->plans->all();
    }

    public function findBySlug(string $slug): ?Plan
    {
        return $this->plans->findBySlug($slug);
    }

    public function isDowngrade(Plan $from, Plan $to): bool
    {
        return $to->sort_order < $from->sort_order;
    }

    /**
     * Checks whether $shop can safely move to $targetPlan. Never
     * deletes or mutates anything — a read-side check the billing flow
     * calls before letting a downgrade proceed.
     *
     * `blockers` (refuses the downgrade until resolved) cover
     * measurable usage: current customer/campaign counts exceeding the
     * target's numeric limits, and any actively-configured VIP tier
     * (e.g. Platinum) the target plan doesn't support. `warnings`
     * (informational only) cover boolean features the current plan has
     * that the target doesn't (CSV export, full API access, advanced
     * notifications) — surfaced but non-blocking, since this app has no
     * per-feature usage log to confirm the merchant actually depends on
     * them; see DowngradeEligibility's docblock for why these two are
     * kept separate.
     */
    public function checkDowngradeEligibility(Shop $shop, Plan $targetPlan): DowngradeEligibility
    {
        $currentPlan = $shop->activeSubscription?->plan;
        $blockers = [];
        $warnings = [];

        $this->checkNumericLimit(
            $shop, $targetPlan, 'max_active_customers', SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS,
            'customer_limit_exceeded', 'active customers', $blockers,
        );
        $this->checkNumericLimit(
            $shop, $targetPlan, 'max_active_point_rules', SubscriptionUsage::METRIC_ACTIVE_POINT_RULES,
            'point_rule_limit_exceeded', 'active reward campaigns', $blockers,
        );

        $this->checkVipTiers($shop, $targetPlan, $blockers);

        if ($currentPlan) {
            $this->checkLostFeatures($currentPlan, $targetPlan, $warnings);
        }

        return empty($blockers)
            ? DowngradeEligibility::allowed($warnings)
            : DowngradeEligibility::blocked($blockers, $warnings);
    }

    private function checkNumericLimit(Shop $shop, Plan $targetPlan, string $limitColumn, string $usageMetric, string $code, string $label, array &$blockers): void
    {
        $limit = $this->usageLimits->limitFor($targetPlan, $limitColumn);
        if ($limit === null) {
            return; // target plan is unlimited for this metric
        }

        $current = $this->usage->current($shop, $usageMetric);

        if ($current > $limit) {
            $blockers[] = [
                'code' => $code,
                'message' => "You have {$current} {$label}, but {$targetPlan->name} allows a maximum of {$limit}. Reduce your {$label} before downgrading.",
            ];
        }
    }

    private function checkVipTiers(Shop $shop, Plan $targetPlan, array &$blockers): void
    {
        $availableOnTarget = $targetPlan->features()
            ->where('group', 'vip_tiers')
            ->wherePivot('value', '1')
            ->pluck('key')
            ->map(fn (string $key) => str($key)->after('vip_tier.')->toString())
            ->all();

        $inUse = VipTier::query()->where('shop_id', $shop->id)->where('is_active', true)->pluck('slug');
        $unavailable = $inUse->diff($availableOnTarget);

        if ($unavailable->isNotEmpty()) {
            $blockers[] = [
                'code' => 'vip_tier_unavailable',
                'message' => "The following VIP tier(s) are not available on {$targetPlan->name}: ".$unavailable->implode(', ').'. Archive or reconfigure them before downgrading.',
            ];
        }
    }

    private function checkLostFeatures(Plan $currentPlan, Plan $targetPlan, array &$warnings): void
    {
        $currentFeatureKeys = $currentPlan->features()->where('group', '!=', 'vip_tiers')->wherePivot('value', '1')->pluck('key');
        $targetFeatureKeys = $targetPlan->features()->where('group', '!=', 'vip_tiers')->wherePivot('value', '1')->pluck('key');

        $lost = $currentFeatureKeys->diff($targetFeatureKeys);

        if ($lost->isNotEmpty()) {
            $warnings[] = [
                'code' => 'feature_unavailable',
                'message' => 'Downgrading will remove: '.$lost->implode(', ').'. Make sure you no longer depend on these before continuing.',
            ];
        }
    }
}
