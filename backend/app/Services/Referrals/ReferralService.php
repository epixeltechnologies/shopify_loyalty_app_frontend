<?php

namespace App\Services\Referrals;

use App\Events\Referrals\ReferralCompleted;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\Shop;
use App\Repositories\Contracts\ReferralRepositoryInterface;

/**
 * REFERRALS: owns the referral lifecycle: pending (link shared) ->
 * completed (referred customer placed a qualifying order) -> rewarded
 * (a `ReferralReward` row is created and points/discount granted via
 * PointsAccrualService). Completion/reward-triggering logic is a
 * business feature and is left for the loyalty-engine milestone — see
 * docs/NEXT_STEPS.md. This class defines the state machine and
 * repository boundary.
 */
class ReferralService
{
    public function __construct(private readonly ReferralRepositoryInterface $referrals) {}

    public function paginate(Shop $shop, int $perPage = 25)
    {
        return $this->referrals->paginateForShop($shop, $perPage);
    }

    public function startReferral(Customer $referrer): Referral
    {
        return $this->referrals->create([
            'shop_id' => $referrer->shop_id,
            'referrer_customer_id' => $referrer->id,
            'referral_code' => $referrer->referral_code,
            'status' => 'pending',
        ]);
    }

    public function markCompleted(Shop $shop, string $code, Customer $referred, string $qualifyingOrderId): ?Referral
    {
        $referral = $this->referrals->findPendingByCode($shop, $code);

        $referral?->update([
            'referred_customer_id' => $referred->id,
            'qualifying_order_id' => $qualifyingOrderId,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($referral) {
            event(new ReferralCompleted($referral));
        }

        // Creating the ReferralReward row(s) (referrer and/or referee)
        // and posting the corresponding points via PointsAccrualService
        // happens in the loyalty-engine milestone, triggered off the
        // ReferralCompleted event above.

        return $referral;
    }
}
