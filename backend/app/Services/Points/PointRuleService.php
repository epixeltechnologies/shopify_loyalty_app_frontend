<?php

namespace App\Services\Points;

use App\Exceptions\Billing\LimitReachedException;
use App\Models\PointRule;
use App\Models\Shop;
use App\Models\SubscriptionUsage;
use App\Repositories\Contracts\PointRuleRepositoryInterface;
use App\Services\Billing\UsageLimitService;
use App\Services\Billing\UsageTrackerService;
use Illuminate\Support\Facades\DB;

/**
 * POINT SYSTEM: manages `PointRule` — how points are earned
 * (points-per-dollar, signup bonus, referral bonus; the task/product
 * vocabulary calls this a "reward campaign" — see
 * EntitlementService::canCreateRewardCampaign()). Creation AND status
 * transitions respect the shop's plan limit on ACTIVE rules only — a
 * `draft` rule never counts against the limit, matching this app's
 * general principle that a merchant can prepare as many drafts as they
 * like; the limit is on what's actually running.
 */
class PointRuleService
{
    public function __construct(
        private readonly PointRuleRepositoryInterface $rules,
        private readonly UsageLimitService $usageLimits,
        private readonly UsageTrackerService $usage,
    ) {}

    public function paginate(Shop $shop, int $perPage = 25)
    {
        return $this->rules->paginateForShop($shop, $perPage);
    }

    /**
     * @throws LimitReachedException if creating an ACTIVE rule and the shop's plan limit is already reached
     */
    public function create(Shop $shop, array $attributes)
    {
        $isActive = ($attributes['status'] ?? 'draft') === 'active';

        if (! $isActive) {
            return $this->rules->create([...$attributes, 'shop_id' => $shop->id]);
        }

        return DB::transaction(function () use ($shop, $attributes) {
            $this->reserveActiveSlotOrFail($shop);

            return $this->rules->create([...$attributes, 'shop_id' => $shop->id]);
        });
    }

    /**
     * Updates a rule, correctly handling every status-transition case:
     * moving INTO `active` reserves a plan slot (throwing
     * `LimitReachedException` if none remain — a merchant can't
     * reactivate a paused rule past their plan's limit any more than
     * they could create a new one over it); moving OUT of `active`
     * releases the slot; anything else is a plain attribute update with
     * no usage-counter change at all.
     *
     * @throws LimitReachedException if reactivating and the shop's plan limit is already reached
     */
    public function update(Shop $shop, PointRule $rule, array $attributes): PointRule
    {
        $wasActive = $rule->status === 'active';
        $willBeActive = ($attributes['status'] ?? $rule->status) === 'active';

        return DB::transaction(function () use ($shop, $rule, $attributes, $wasActive, $willBeActive) {
            if (! $wasActive && $willBeActive) {
                $this->reserveActiveSlotOrFail($shop);
            } elseif ($wasActive && ! $willBeActive) {
                $this->usage->decrement($shop, SubscriptionUsage::METRIC_ACTIVE_POINT_RULES);
            }

            $rule->update($attributes);

            return $rule->fresh();
        });
    }

    private function reserveActiveSlotOrFail(Shop $shop): void
    {
        $plan = $shop->entitlements()->plan();

        // Atomic check-and-reserve, same race-condition rationale as
        // CustomerService::enroll() — see
        // UsageTrackerService::tryIncrementIfUnderLimit()'s docblock.
        if (! $this->usageLimits->tryReservePointRuleSlot($shop, $plan)) {
            $limit = $this->usageLimits->limitFor($plan, 'max_active_point_rules');

            throw new LimitReachedException(
                feature: 'active_reward_campaigns',
                currentUsage: $limit ?? 0,
                limit: $limit ?? 0,
                requiredPlan: $plan?->slug === 'starter' ? 'professional' : null,
            );
        }
    }
}
