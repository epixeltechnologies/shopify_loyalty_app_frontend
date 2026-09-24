<?php

namespace App\Jobs\Referrals;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Referral;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched daily (see routes/console.php) — the "referral expiration"
 * requirement: a `clicked`/`registered` referral whose attribution
 * window has closed without ever producing a qualifying order is moved
 * to `cancelled` rather than sitting in limbo forever. A `qualified`
 * referral is NEVER touched here regardless of age — it has already
 * met every requirement and is simply waiting out its reward delay
 * (`ProcessReferralRewardsJob`'s job, not this one's).
 */
class ExpireStaleReferralsJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Shop $shop) {}

    public function handle(): void
    {
        Referral::query()
            ->where('shop_id', $this->shop->id)
            ->whereIn('status', [Referral::STATUS_CLICKED, Referral::STATUS_REGISTERED])
            ->where('attribution_expires_at', '<=', now())
            ->update(['status' => Referral::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }
}
