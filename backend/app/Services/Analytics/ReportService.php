<?php

namespace App\Services\Analytics;

use App\Models\Customer;
use App\Models\CustomerVipHistory;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ANALYTICS: the seven report types the task requires. Every report
 * takes a resolved `[start, end]` window (see DateRangeResolver — never
 * duplicated per report) and returns a `summary` (headline totals) plus
 * either a `trend` (daily series, read from the pre-aggregated
 * `analytics_daily_snapshots` table — never a live aggregation over raw
 * rows) or a `breakdown`/paginated detail list (read from source
 * tables, since these need finer grouping than a daily snapshot
 * captures — e.g. "most popular rewards" groups by reward, not by day).
 *
 * This class is the single source of truth both the JSON report
 * endpoints AND the CSV export job read from — ReportExportService
 * calls the same methods, never a parallel export-specific query, so a
 * report and its export can never silently disagree.
 */
class ReportService
{
    public function __construct(private readonly AnalyticsRepositoryInterface $analytics) {}

    public function customerGrowth(Shop $shop, Carbon $start, Carbon $end): array
    {
        return [
            'summary' => [
                'total_customers' => Customer::query()->where('shop_id', $shop->id)->where('enrolled_at', '<=', $end)->count(),
                'new_customers' => Customer::query()->where('shop_id', $shop->id)->whereBetween('enrolled_at', [$start, $end])->count(),
                'active_customers' => $this->activeCustomerCount($shop, $start, $end),
                'returning_customers' => $this->returningCustomerCount($shop, $start, $end),
            ],
            'trend' => $this->trend($shop, ['new_customers', 'active_members'], $start, $end),
        ];
    }

    public function points(Shop $shop, Carbon $start, Carbon $end): array
    {
        $earned = (int) PointTransaction::query()->where('shop_id', $shop->id)->where('direction', 'earn')->whereBetween('created_at', [$start, $end])->sum('points');
        $redeemed = (int) abs(PointTransaction::query()->where('shop_id', $shop->id)->where('direction', 'redeem')->whereBetween('created_at', [$start, $end])->sum('points'));
        $expired = (int) abs(PointTransaction::query()->where('shop_id', $shop->id)->where('direction', 'expire')->whereBetween('created_at', [$start, $end])->sum('points'));
        $adjusted = (int) PointTransaction::query()->where('shop_id', $shop->id)->where('direction', 'adjust')->whereBetween('created_at', [$start, $end])->sum('points');
        $activeCustomers = max(1, $this->activeCustomerCount($shop, $start, $end)); // avoid div-by-zero; a period with zero activity has zero averages anyway

        return [
            'summary' => [
                'total_earned' => $earned,
                'total_redeemed' => $redeemed,
                'total_expired' => $expired,
                'total_adjusted' => $adjusted,
                // Outstanding liability is a POINT-IN-TIME balance
                // (as of `end`, not "within the window") — computed from
                // the current `points` table, the same authoritative
                // source PointAccountService reads, not derived by
                // summing this window's transactions.
                'outstanding_liability' => (int) DB::table('points')->where('shop_id', $shop->id)->sum('balance'),
                'average_earned_per_customer' => round($earned / $activeCustomers, 2),
                'average_redeemed_per_customer' => round($redeemed / $activeCustomers, 2),
            ],
            'trend' => $this->trend($shop, ['points_issued', 'points_redeemed', 'points_expired'], $start, $end),
        ];
    }

    public function rewards(Shop $shop, Carbon $start, Carbon $end): array
    {
        $totalRewards = Reward::query()->where('shop_id', $shop->id)->count();
        $activeRewards = Reward::query()->where('shop_id', $shop->id)->where('status', 'active')->count();
        $totalRedemptions = RewardRedemption::query()->where('shop_id', $shop->id)->whereBetween('created_at', [$start, $end])->count();
        $completedRedemptions = RewardRedemption::query()->where('shop_id', $shop->id)->where('status', 'completed')->whereBetween('created_at', [$start, $end])->count();

        return [
            'summary' => [
                'total_rewards' => $totalRewards,
                'active_rewards' => $activeRewards,
                'total_redemptions' => $totalRedemptions,
                // Of every redemption ATTEMPT in the window, what fraction actually completed successfully.
                'redemption_success_rate' => $totalRedemptions > 0 ? round($completedRedemptions / $totalRedemptions, 4) : 0.0,
            ],
            'most_popular_rewards' => RewardRedemption::query()
                ->where('shop_id', $shop->id)
                ->whereBetween('created_at', [$start, $end])
                ->join('rewards', 'rewards.id', '=', 'reward_redemptions.reward_id')
                ->groupBy('rewards.id', 'rewards.name')
                ->orderByDesc('redemption_count')
                ->limit(10)
                ->selectRaw('rewards.id, rewards.name, COUNT(*) as redemption_count')
                ->get(),
            'trend' => $this->trend($shop, ['reward_redemptions'], $start, $end),
        ];
    }

    public function redemptions(Shop $shop, Carbon $start, Carbon $end, int $perPage = 25)
    {
        return RewardRedemption::query()
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$start, $end])
            ->with(['customer', 'reward'])
            ->latest()
            ->paginate($perPage);
    }

    public function referrals(Shop $shop, Carbon $start, Carbon $end): array
    {
        $created = Referral::query()->where('shop_id', $shop->id)->whereBetween('created_at', [$start, $end])->count();
        $registered = Referral::query()->where('shop_id', $shop->id)->whereBetween('registered_at', [$start, $end])->count();
        $qualified = Referral::query()->where('shop_id', $shop->id)->whereBetween('qualified_at', [$start, $end])->count();
        $rewarded = Referral::query()->where('shop_id', $shop->id)->whereBetween('rewarded_at', [$start, $end])->count();

        return [
            'summary' => [
                'invitations' => $created,
                'registrations' => $registered,
                'qualified' => $qualified,
                'successful' => $rewarded,
                'conversion_rate' => $created > 0 ? round($rewarded / $created, 4) : 0.0,
                'rewards_issued' => DB::table('referral_rewards')
                    ->where('shop_id', $shop->id)
                    ->whereBetween('granted_at', [$start, $end])
                    ->count(),
            ],
            'trend' => $this->trend($shop, ['referrals_created', 'referrals_qualified', 'referrals_completed'], $start, $end),
        ];
    }

    public function vip(Shop $shop, Carbon $start, Carbon $end): array
    {
        $distribution = DB::table('customers')
            ->join('vip_tiers', 'vip_tiers.id', '=', 'customers.vip_tier_id')
            ->where('customers.shop_id', $shop->id)
            ->groupBy('vip_tiers.id', 'vip_tiers.name', 'vip_tiers.sort_order')
            ->orderBy('vip_tiers.sort_order')
            ->selectRaw('vip_tiers.id, vip_tiers.name, COUNT(*) as customer_count')
            ->get();

        return [
            'summary' => [
                'vip_customers' => Customer::query()->where('shop_id', $shop->id)->whereNotNull('vip_tier_id')->count(),
                'upgrades' => CustomerVipHistory::query()->where('shop_id', $shop->id)->where('direction', 'upgrade')->whereBetween('created_at', [$start, $end])->count(),
                'downgrades' => CustomerVipHistory::query()->where('shop_id', $shop->id)->where('direction', 'downgrade')->whereBetween('created_at', [$start, $end])->count(),
            ],
            'distribution' => $distribution,
            'trend' => $this->trend($shop, ['vip_upgrades', 'vip_downgrades'], $start, $end),
        ];
    }

    public function loyaltyEngagement(Shop $shop, Carbon $start, Carbon $end): array
    {
        $totalCustomers = max(1, Customer::query()->where('shop_id', $shop->id)->where('enrolled_at', '<=', $end)->count());
        $activeCustomers = $this->activeCustomerCount($shop, $start, $end);

        return [
            'summary' => [
                // What fraction of everyone ever enrolled did SOMETHING
                // (earned/redeemed/adjusted points) in this window —
                // the headline "is the program actually being used" number.
                'participation_rate' => round($activeCustomers / $totalCustomers, 4),
                'active_customers' => $activeCustomers,
                'returning_customers' => $this->returningCustomerCount($shop, $start, $end),
            ],
            'earning_trend' => $this->trend($shop, ['points_issued'], $start, $end),
            'redemption_trend' => $this->trend($shop, ['points_redeemed'], $start, $end),
        ];
    }

    /** @return array<string, \Illuminate\Support\Collection> */
    private function trend(Shop $shop, array $metrics, Carbon $start, Carbon $end): array
    {
        $series = [];
        foreach ($metrics as $metric) {
            $series[$metric] = $this->analytics->metricSeries($shop, $metric, $start, $end);
        }

        return $series;
    }

    private function activeCustomerCount(Shop $shop, Carbon $start, Carbon $end): int
    {
        return (int) PointTransaction::query()
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('customer_id')
            ->count('customer_id');
    }

    /** A customer who earned points on more than one distinct day within the window — a simple, honest proxy for "came back," not a full session/visit model this app doesn't track. */
    private function returningCustomerCount(Shop $shop, Carbon $start, Carbon $end): int
    {
        return (int) DB::table('point_transactions')
            ->where('shop_id', $shop->id)
            ->where('direction', 'earn')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(DISTINCT DATE(created_at)) > 1')
            ->get()
            ->count();
    }
}
