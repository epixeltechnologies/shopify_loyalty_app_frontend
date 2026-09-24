<?php

namespace App\Listeners\Referrals;

use App\Events\Referrals\ReferralCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Placeholder: posts the referral-bonus points to the referrer via
 * PointsAccrualService once campaign-evaluation logic exists — see
 * docs/NEXT_STEPS.md step 5.
 */
class RewardReferrerOnCompletion implements ShouldQueue
{
    public function handle(ReferralCompleted $event): void
    {
        // $this->accrual->accrueForReferral($event->referral->referrer, $event->referral);
    }
}
