<?php

namespace App\Services\Analytics;

use App\Models\Customer;
use App\Models\CustomerVipHistory;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * ANALYTICS: invoked by a nightly scheduled job
 * (BuildDailyAnalyticsSnapshotsJob, see routes/console.php) to compute
 * one shop's metrics for one day and upsert them into
 * `analytics_daily_snapshots` — the read path every dashboard/report
 * query hits, so this is the only place expensive aggregation ever runs.
 *
 * DESIGN CHOICE — reads source-of-truth tables directly, not
 * `analytics_events`: every metric here (points issued/redeemed/expired,
 * reward redemptions, referral funnel, VIP transitions, customer counts)
 * already has a fully authoritative table with its own timestamp
 * column (`point_transactions`, `reward_redemptions`, `referrals`,
 * `customer_vip_history`, `customers`) — computing from THOSE directly
 * is both simpler and strictly more accurate than replaying
 * `analytics_events` as an intermediate hop, since an aggregate number
 * should never have a chance to disagree with its own ledger. The
 * `analytics_events` feed (see EventRecorder) remains valuable as a
 * uniform, low-friction way to add tracking for a FUTURE metric that
 * has no natural source table of its own — this class would read from
 * it for exactly that case, not for anything computed here today.
 */
class SnapshotBuilder
{
    public function __construct(private readonly AnalyticsRepositoryInterface $analytics) {}

    public function buildForShop(Shop $shop, \DateTimeInterface $date): void
    {
        [$start, $end] = $this->dayWindow($shop, $date);

        foreach ($this->metrics($shop, $start, $end) as $metric => $value) {
            $this->analytics->upsertSnapshot($shop, $date, $metric, $value);
        }
    }

    /** The day's boundaries in the SHOP's own timezone, normalized to UTC — see DateRangeResolver's docblock for why this conversion matters. */
    private function dayWindow(Shop $shop, \DateTimeInterface $date): array
    {
        $tz = $shop->timezone ?: 'UTC';
        $day = Carbon::parse($date, $tz);

        return [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()];
    }

    /** @return array<string, float> */
    private function metrics(Shop $shop, Carbon $start, Carbon $end): array
    {
        return [
            // --- Customers ---------------------------------------------
            'new_customers' => (float) Customer::query()
                ->where('shop_id', $shop->id)
                ->whereBetween('enrolled_at', [$start, $end])
                ->count(),
            'active_members' => (float) PointTransaction::query()
                ->where('shop_id', $shop->id)
                ->whereBetween('created_at', [$start, $end])
                ->distinct('customer_id')
                ->count('customer_id'),
            'total_customers' => (float) Customer::query()
                ->where('shop_id', $shop->id)
                ->where('enrolled_at', '<=', $end)
                ->count(),

            // --- Points --------------------------------------------------
            'points_issued' => (float) PointTransaction::query()
                ->where('shop_id', $shop->id)->where('direction', PointTransaction::DIRECTION_EARN)
                ->whereBetween('created_at', [$start, $end])->sum('points'),
            'points_redeemed' => (float) abs(PointTransaction::query()
                ->where('shop_id', $shop->id)->where('direction', PointTransaction::DIRECTION_REDEEM)
                ->whereBetween('created_at', [$start, $end])->sum('points')),
            'points_expired' => (float) abs(PointTransaction::query()
                ->where('shop_id', $shop->id)->where('direction', PointTransaction::DIRECTION_EXPIRE)
                ->whereBetween('created_at', [$start, $end])->sum('points')),
            'points_adjusted' => (float) PointTransaction::query()
                ->where('shop_id', $shop->id)->where('direction', PointTransaction::DIRECTION_ADJUST)
                ->whereBetween('created_at', [$start, $end])->sum('points'),

            // --- Rewards ---------------------------------------------------
            'reward_redemptions' => (float) RewardRedemption::query()
                ->where('shop_id', $shop->id)->where('status', RewardRedemption::STATUS_COMPLETED)
                ->whereBetween('created_at', [$start, $end])->count(),

            // --- Referrals ---------------------------------------------------
            'referrals_created' => (float) Referral::query()
                ->where('shop_id', $shop->id)->whereBetween('created_at', [$start, $end])->count(),
            'referrals_registered' => (float) Referral::query()
                ->where('shop_id', $shop->id)->whereBetween('registered_at', [$start, $end])->count(),
            'referrals_qualified' => (float) Referral::query()
                ->where('shop_id', $shop->id)->whereBetween('qualified_at', [$start, $end])->count(),
            // "referrals_completed" kept as the historical metric name
            // AnalyticsPage/tests were already built against (see
            // docs/FRONTEND_ARCHITECTURE.md) — semantically the same
            // thing as "rewarded" for a referral.
            'referrals_completed' => (float) Referral::query()
                ->where('shop_id', $shop->id)->whereBetween('rewarded_at', [$start, $end])->count(),

            // --- VIP ---------------------------------------------------
            'vip_upgrades' => (float) CustomerVipHistory::query()
                ->where('shop_id', $shop->id)->where('direction', 'upgrade')
                ->whereBetween('created_at', [$start, $end])->count(),
            'vip_downgrades' => (float) CustomerVipHistory::query()
                ->where('shop_id', $shop->id)->where('direction', 'downgrade')
                ->whereBetween('created_at', [$start, $end])->count(),
        ];
    }
}
