<?php

namespace App\Jobs\Referrals;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Referral;
use App\Models\Shop;
use App\Services\Referrals\ReferralRewardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched on a schedule (see routes/console.php) — finds every
 * `qualified` referral whose configurable reward delay has elapsed and
 * pays it out via `ReferralRewardService`. A referral flagged for
 * fraud review is skipped entirely (never rewarded) until a merchant
 * clears it — see `ReferralController::clearFraud()`/`confirmFraud()`.
 */
class ProcessReferralRewardsJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Shop $shop) {}

    public function handle(ReferralRewardService $rewards): void
    {
        $due = Referral::query()
            ->where('shop_id', $this->shop->id)
            ->where('status', Referral::STATUS_QUALIFIED)
            ->where('fraud_status', '!=', Referral::FRAUD_STATUS_FLAGGED)
            ->where('fraud_status', '!=', Referral::FRAUD_STATUS_CONFIRMED)
            ->where('reward_scheduled_at', '<=', now())
            ->get();

        $rewarded = 0;
        foreach ($due as $referral) {
            if ($rewards->reward($referral)) {
                $rewarded++;
            }
        }

        if ($rewarded > 0) {
            Log::info('Referral rewards processed', ['shop' => $this->shop->shopify_domain, 'count' => $rewarded]);
        }
    }
}
