<?php

namespace App\Services\VipTiers;

use App\Models\Customer;
use App\Models\VipSettings;
use App\Models\VipTier;
use App\Repositories\Contracts\VipTierRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * VIP: decides a customer's correct tier and delegates applying the
 * change to VipTierService — this class only computes, never writes.
 *
 * Tiers a customer qualifies for are compared by `sort_order` (the
 * merchant's own explicit tier ranking), never by raw threshold value —
 * different qualification methods use incomparable scales (points vs.
 * cents vs. order count), so "highest threshold" is meaningless across
 * tiers using different methods; "highest sort_order among tiers this
 * customer qualifies for" is the only comparison that's always correct.
 *
 * UPGRADE VS. DOWNGRADE TIMING — the task's core requirement here:
 *   - `evaluateForUpgradeOnly()`: called synchronously off every
 *     `PointsPosted` event. Only ever moves a customer UP — if the
 *     newly-computed tier is lower than or equal to their current one,
 *     this is a no-op. A single points transaction (a redemption, an
 *     expiry, an adjustment) should never immediately cost a customer
 *     their tier; only a scheduled evaluation may do that.
 *   - `evaluateFully()`: called ONLY from the scheduled batch job
 *     (`EvaluateShopVipTiersJob`) or a manual admin-triggered
 *     evaluation. Applies either direction. This is what makes
 *     "do not immediately downgrade due to a temporary webhook delay"
 *     true — a downgrade only ever happens on the daily schedule's own
 *     terms, giving refunds/cancellations/corrections time to settle
 *     first.
 */
class VipEvaluationService
{
    public function __construct(
        private readonly VipTierRepositoryInterface $tiers,
        private readonly VipQualificationService $qualification,
        private readonly VipTierService $tierService,
    ) {}

    public function evaluateForUpgradeOnly(Customer $customer): void
    {
        if (! $this->programEnabled($customer)) {
            return;
        }

        $target = $this->resolveBestQualifyingTier($customer);
        $current = $customer->vipTier;

        if (! $this->isUpgrade($current, $target)) {
            return;
        }

        $this->tierService->applyTierChange($customer, $target, $this->reasonFor($customer, $target));
    }

    public function evaluateFully(Customer $customer): void
    {
        if (! $this->programEnabled($customer)) {
            return;
        }

        $target = $this->resolveBestQualifyingTier($customer);

        if ($target?->id === $customer->vip_tier_id) {
            return;
        }

        $this->tierService->applyTierChange($customer, $target, $this->reasonFor($customer, $target));
    }

    /** The shop-level VIP program switch (docs/SETTINGS.md) — defaults to enabled when no VipSettings row exists yet, matching every other settings table's "sensible default until the merchant configures it" convention. */
    private function programEnabled(Customer $customer): bool
    {
        $settings = VipSettings::query()->where('shop_id', $customer->shop_id)->first();

        return $settings?->enabled ?? true;
    }

    /** The highest-`sort_order` tier (among this shop's active, plan-available, date-windowed tiers) this customer currently qualifies for — null if none. */
    private function resolveBestQualifyingTier(Customer $customer): ?VipTier
    {
        return $this->eligibleTiers($customer->shop_id, $customer)
            ->filter(fn (VipTier $tier) => $this->qualification->qualifies($customer, $tier))
            ->sortByDesc('sort_order')
            ->first();
    }

    private function eligibleTiers(int $shopId, Customer $customer): Collection
    {
        return $this->tiers->allForShop($customer->shop)
            ->filter(fn (VipTier $tier) => $tier->is_active && $tier->isWithinDateWindow() && $tier->isAvailableToShop());
    }

    private function isUpgrade(?VipTier $current, ?VipTier $target): bool
    {
        if (! $target) {
            return false; // never "upgrades" a customer to no tier
        }

        return ($target->sort_order ?? 0) > ($current->sort_order ?? -1);
    }

    private function reasonFor(Customer $customer, ?VipTier $target): ?string
    {
        return $target ? $this->qualification->describeQualification($customer, $target) : null;
    }
}
