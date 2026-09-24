<?php

namespace App\Services\Rewards;

use App\Exceptions\Rewards\CustomerNotEligibleException;
use App\Exceptions\Rewards\RewardNotRedeemableException;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\VipTier;

/**
 * Every non-balance redemption precondition — reward state, date
 * window, stock, usage limits, and customer eligibility. Deliberately
 * separate from the balance check (which needs the already-locked
 * `Point` row — see RewardRedemptionService) so this class can be unit
 * tested without touching the ledger at all.
 *
 * Callers MUST hold a `lockForUpdate()` lock on the `Reward` row before
 * calling the usage-limit checks here — this class itself does no
 * locking; it queries `reward_redemptions` counts which are only race-
 * free because the caller's lock serializes concurrent redemption
 * attempts for this specific reward. See RewardRedemptionService.
 */
class RewardEligibilityService
{
    /** @throws RewardNotRedeemableException */
    public function assertRedeemable(Reward $reward): void
    {
        if (! $reward->isActive()) {
            throw new RewardNotRedeemableException('not_active', 'This reward is not currently active.');
        }

        if (! $reward->isWithinDateWindow()) {
            throw new RewardNotRedeemableException('expired', 'This reward is outside its active date window.');
        }

        if ($reward->stock_limit !== null && $reward->activeRedemptions()->count() >= $reward->stock_limit) {
            throw new RewardNotRedeemableException('out_of_stock', 'This reward is out of stock.');
        }

        if ($reward->max_total_redemptions !== null && $reward->activeRedemptions()->count() >= $reward->max_total_redemptions) {
            throw new RewardNotRedeemableException('redemption_limit_reached', 'This reward has reached its total redemption limit.');
        }
    }

    /** @throws RewardNotRedeemableException */
    public function assertWithinCustomerLimit(Reward $reward, Customer $customer): void
    {
        if ($reward->max_redemptions_per_customer === null) {
            return;
        }

        $existing = $reward->activeRedemptions()->where('customer_id', $customer->id)->count();

        if ($existing >= $reward->max_redemptions_per_customer) {
            throw new RewardNotRedeemableException(
                'customer_limit_reached',
                $reward->max_redemptions_per_customer === 1
                    ? 'This reward can only be redeemed once per customer.'
                    : "This reward can only be redeemed {$reward->max_redemptions_per_customer} times per customer."
            );
        }
    }

    /**
     * @throws CustomerNotEligibleException
     */
    public function assertCustomerEligible(Reward $reward, Customer $customer): void
    {
        $rule = $reward->customer_eligibility;

        if (empty($rule) || ($rule['type'] ?? 'all') === 'all') {
            return;
        }

        match ($rule['type']) {
            'vip_tier_minimum' => $this->assertMeetsMinimumVipTier($customer, (int) $rule['vip_tier_id']),
            'specific_customers' => $this->assertInCustomerList($customer, $rule['customer_ids'] ?? []),
            default => null, // unknown eligibility type — fails open rather than blocking all redemptions on a config typo
        };
    }

    private function assertMeetsMinimumVipTier(Customer $customer, int $requiredVipTierId): void
    {
        $requiredTier = VipTier::query()->find($requiredVipTierId);
        if (! $requiredTier) {
            return; // the configured tier no longer exists — fails open, same rationale as the default match arm above
        }

        $customerTier = $customer->vipTier;
        if (! $customerTier || $customerTier->sort_order < $requiredTier->sort_order) {
            throw new CustomerNotEligibleException("This reward requires {$requiredTier->name} tier or higher.");
        }
    }

    private function assertInCustomerList(Customer $customer, array $allowedCustomerIds): void
    {
        if (! in_array($customer->id, $allowedCustomerIds, true)) {
            throw new CustomerNotEligibleException();
        }
    }
}
