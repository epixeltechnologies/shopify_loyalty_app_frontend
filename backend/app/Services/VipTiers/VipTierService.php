<?php

namespace App\Services\VipTiers;

use App\Events\VipTiers\VipTierChanged;
use App\Models\Customer;
use App\Models\CustomerVipHistory;
use App\Models\Shop;
use App\Models\VipTier;
use App\Repositories\Contracts\VipTierRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * VIP: reads/writes `vip_tiers` and is the ONLY code path allowed to
 * change a customer's tier — `applyTierChange()` is where
 * `customers.vip_tier_id` is updated, `customer_vip_history` is
 * written, and `VipTierChanged` fires, all in one transaction. Tier
 * *creation* is gated by plan features (vip_tier.silver/gold/platinum)
 * — see EnsureFeatureEntitlement/VipTierPolicy — not by anything here.
 *
 * WHICH tier a customer should move to is decided by VipEvaluationService,
 * never computed in this class — this keeps "decide" and "apply"
 * cleanly separated, matching the task's explicit 4-service architecture.
 */
class VipTierService
{
    public function __construct(private readonly VipTierRepositoryInterface $tiers) {}

    public function listForShop(Shop $shop): Collection
    {
        return $this->tiers->allForShop($shop);
    }

    /**
     * Idempotent: a no-op if `$newTier` is already the customer's
     * current tier — safe to call from a retried job or a re-run
     * evaluation without creating duplicate history rows or re-firing
     * the event.
     */
    public function applyTierChange(Customer $customer, ?VipTier $newTier, ?string $reason = null): void
    {
        if ($newTier?->id === $customer->vip_tier_id) {
            return;
        }

        DB::transaction(function () use ($customer, $newTier, $reason) {
            $previousTierId = $customer->vip_tier_id;
            $previousTier = $previousTierId ? VipTier::query()->find($previousTierId) : null;
            $direction = $this->resolveDirection($previousTier, $newTier);

            $customer->update(['vip_tier_id' => $newTier?->id, 'vip_tier_evaluated_at' => now()]);

            CustomerVipHistory::query()->create([
                'shop_id' => $customer->shop_id,
                'customer_id' => $customer->id,
                'from_vip_tier_id' => $previousTierId,
                'to_vip_tier_id' => $newTier?->id,
                'direction' => $direction,
                'qualification_reason' => $reason,
                'evaluation_period' => $newTier?->evaluation_period,
                'lifetime_points_at_change' => $customer->point?->lifetime_earned ?? 0,
                'effective_date' => now(),
            ]);

            event(new VipTierChanged($customer, $previousTierId, $newTier?->id, $direction));
        });
    }

    /**
     * Customers currently ASSIGNED to a tier their shop's plan no
     * longer entitles it to (a post-downgrade Platinum customer on a
     * Starter plan) — the "show downgrade warning, require merchant
     * action" surface. Nothing here changes automatically; scheduled
     * evaluation deliberately never demotes these customers on its
     * own — see VipEvaluationService/docs/VIP_TIERS.md.
     */
    public function customersAffectedByPlanDowngrade(Shop $shop): Collection
    {
        return $this->tiers->allForShop($shop)
            ->filter(fn (VipTier $tier) => ! $tier->isAvailableToShop())
            ->flatMap(fn (VipTier $tier) => $tier->customers()->get());
    }

    private function resolveDirection(?VipTier $previous, ?VipTier $new): string
    {
        if (! $previous) {
            return 'initial';
        }

        return ($new?->sort_order ?? -1) >= $previous->sort_order ? 'upgrade' : 'downgrade';
    }
}
