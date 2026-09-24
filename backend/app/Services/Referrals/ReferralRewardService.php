<?php

namespace App\Services\Referrals;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Services\Analytics\EventRecorder;
use App\Services\Notifications\NotificationService;
use App\Services\Points\PointsAccrualService;
use Illuminate\Support\Facades\DB;

/**
 * REFERRALS: pays out a `qualified` referral once its reward delay has
 * elapsed — invoked by `ProcessReferralRewardsJob` (scheduled), never
 * called directly from the qualification path itself, which is exactly
 * what makes the configurable delay real rather than cosmetic.
 *
 * Reuses `PointsAccrualService::accrueReferralReward()` for BOTH sides
 * of a double-sided program (see that method's docblock) — this class
 * never touches the ledger directly, only decides WHETHER and HOW MUCH
 * to pay, matching the "never directly modify the customer points
 * balance" requirement.
 */
class ReferralRewardService
{
    public function __construct(
        private readonly ReferralSettingsService $settingsService,
        private readonly PointsAccrualService $accrual,
        private readonly EventRecorder $events,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return bool whether a reward was granted (false for a referral that turned out to have nothing to pay, e.g. both reward amounts are 0/unconfigured)
     */
    public function reward(Referral $referral): bool
    {
        $transitionedNow = false;

        $grantedAny = DB::transaction(function () use ($referral, &$transitionedNow) {
            $locked = Referral::query()->where('id', $referral->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Referral::STATUS_QUALIFIED) {
                return false; // already rewarded/rejected by a concurrent run — idempotent
            }

            $transitionedNow = true;
            $settings = $this->settingsService->getOrCreate($locked->shop);
            $grantedAny = false;

            if ($settings->referrer_reward_points && $locked->referrer) {
                $this->grant($locked, $locked->referrer, 'referrer', $settings->referrer_reward_points, PointTransaction::SOURCE_REFERRAL_REWARD);
                $grantedAny = true;
            }

            if ($settings->referee_reward_points && $locked->referred) {
                $this->grant($locked, $locked->referred, 'referee', $settings->referee_reward_points, PointTransaction::SOURCE_REFERRAL_BONUS);
                $grantedAny = true;
            }

            $locked->update(['status' => Referral::STATUS_REWARDED, 'rewarded_at' => now()]);

            return $grantedAny;
        });

        // Fires exactly once per referral, on whichever call actually
        // performs the transition — never on the idempotent no-op path
        // a concurrent/retried run would otherwise take, which would
        // double-count this referral in "referrals rewarded" reporting.
        if ($transitionedNow) {
            $this->events->record($referral->shop, 'referral.rewarded', $referral->referrer, ['referral_id' => $referral->id]);
        }

        return $grantedAny;
    }

    private function grant(Referral $referral, Customer $beneficiaryCustomer, string $beneficiary, int $points, string $source): void
    {
        $transaction = $this->accrual->accrueReferralReward($beneficiaryCustomer, $referral->id, $points, $source);

        if (! $transaction) {
            return;
        }

        // Idempotent the same way every other reward-record creation in
        // this app is: keyed to (referral, beneficiary), so a re-run of
        // this method for an already-rewarded referral (which the
        // status-guard above should already prevent, but this is a
        // second, independent backstop) never creates a duplicate
        // ReferralReward row even if it somehow got this far twice.
        ReferralReward::query()->firstOrCreate(
            ['shop_id' => $referral->shop_id, 'referral_id' => $referral->id, 'beneficiary' => $beneficiary],
            [
                'customer_id' => $beneficiaryCustomer->id,
                'reward_type' => 'points',
                'points_awarded' => $points,
                'point_transaction_id' => $transaction->id,
                'granted_at' => now(),
            ],
        );

        $this->notifications->notify($referral->shop, $beneficiaryCustomer, 'referral_reward', ['points' => (string) $points]);
    }
}
