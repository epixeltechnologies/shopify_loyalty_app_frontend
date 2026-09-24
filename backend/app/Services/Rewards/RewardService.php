<?php

namespace App\Services\Rewards;

use App\Exceptions\Billing\LimitReachedException;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\Shop;
use App\Models\SubscriptionUsage;
use App\Repositories\Contracts\RewardRepositoryInterface;
use App\Services\Billing\UsageLimitService;
use App\Services\Billing\UsageTrackerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manages the redeemable catalog (Reward) — the "what points can be
 * spent on" side of the rewards domain, distinct from PointRuleService
 * (point_rules — how points are earned). Creation AND status
 * transitions respect the shop's plan limit on ACTIVE rewards only —
 * see `max_active_rewards` migration's note on this being a distinct,
 * correctly-scoped limit from the point-rule one.
 */
class RewardService
{
    public function __construct(
        private readonly RewardRepositoryInterface $rewards,
        private readonly RewardEligibilityService $eligibility,
        private readonly UsageLimitService $usageLimits,
        private readonly UsageTrackerService $usage,
    ) {}

    public function paginate(Shop $shop, int $perPage = 25)
    {
        return $this->rewards->paginateForShop($shop, $perPage);
    }

    public function catalog(Shop $shop)
    {
        return $this->rewards->activeCatalogForShop($shop);
    }

    /**
     * The catalog filtered to what a SPECIFIC customer can currently
     * redeem — active, within its date window, not out of stock/at its
     * total-redemption cap, not at ITS per-customer cap, and passing
     * customer_eligibility. This is read-only filtering for display
     * purposes (the "Customer Experience Foundation" endpoints) — the
     * actual redemption attempt re-validates everything from scratch
     * under a lock (see RewardRedemptionService), since a reward's
     * availability can change between "list what's eligible" and
     * "attempt to redeem."
     */
    public function eligibleFor(Customer $customer): Collection
    {
        return $this->catalog($customer->shop)->filter(function (Reward $reward) use ($customer) {
            try {
                $this->eligibility->assertRedeemable($reward);
                $this->eligibility->assertWithinCustomerLimit($reward, $customer);
                $this->eligibility->assertCustomerEligible($reward, $customer);

                return true;
            } catch (\Throwable) {
                return false;
            }
        })->values();
    }

    /**
     * @throws LimitReachedException if creating an ACTIVE reward and the shop's plan limit is already reached
     */
    public function create(Shop $shop, array $attributes)
    {
        $isActive = ($attributes['status'] ?? 'draft') === 'active';

        if (! $isActive) {
            return $this->rewards->create([...$attributes, 'shop_id' => $shop->id]);
        }

        return DB::transaction(function () use ($shop, $attributes) {
            $this->reserveActiveSlotOrFail($shop);

            return $this->rewards->create([...$attributes, 'shop_id' => $shop->id]);
        });
    }

    /**
     * Same status-transition handling as PointRuleService::update() —
     * moving INTO `active` reserves a plan slot, moving OUT releases
     * it, anything else is a plain update.
     *
     * @throws LimitReachedException if reactivating and the shop's plan limit is already reached
     */
    public function update(Shop $shop, Reward $reward, array $attributes): Reward
    {
        $wasActive = $reward->status === Reward::STATUS_ACTIVE;
        $willBeActive = ($attributes['status'] ?? $reward->status) === Reward::STATUS_ACTIVE;

        return DB::transaction(function () use ($shop, $reward, $attributes, $wasActive, $willBeActive) {
            if (! $wasActive && $willBeActive) {
                $this->reserveActiveSlotOrFail($shop);
            } elseif ($wasActive && ! $willBeActive) {
                $this->usage->decrement($shop, SubscriptionUsage::METRIC_ACTIVE_REWARDS);
            }

            $reward->update($attributes);

            return $reward->fresh();
        });
    }

    private function reserveActiveSlotOrFail(Shop $shop): void
    {
        $plan = $shop->entitlements()->plan();

        if (! $this->usageLimits->tryReserveRewardSlot($shop, $plan)) {
            $limit = $this->usageLimits->limitFor($plan, 'max_active_rewards');

            throw new LimitReachedException(
                feature: 'active_rewards',
                currentUsage: $limit ?? 0,
                limit: $limit ?? 0,
                requiredPlan: $plan?->slug === 'starter' ? 'professional' : null,
            );
        }
    }
}
