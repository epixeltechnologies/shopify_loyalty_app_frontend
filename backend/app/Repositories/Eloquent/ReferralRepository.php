<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Referral;
use App\Models\Shop;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReferralRepository implements ReferralRepositoryInterface
{
    public function paginateForShop(Shop $shop, int $perPage = 25, ?string $status = null)
    {
        return Referral::query()
            ->where('shop_id', $shop->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['referrer', 'referred'])
            ->latest()
            ->paginate($perPage);
    }

    public function paginateForCustomer(Customer $customer, int $perPage = 25)
    {
        return Referral::query()
            ->where('referrer_customer_id', $customer->id)
            ->latest()
            ->paginate($perPage);
    }

    public function findPendingByCode(Shop $shop, string $code): ?Referral
    {
        return Referral::query()
            ->where('shop_id', $shop->id)
            ->where('referral_code', $code)
            ->where('status', 'pending')
            ->first();
    }

    public function create(array $attributes): Referral
    {
        return Referral::query()->create($attributes);
    }

    public function countCompletedForCustomer(Customer $customer): int
    {
        return Referral::query()
            ->where('referrer_customer_id', $customer->id)
            ->whereIn('status', ['completed', 'rewarded'])
            ->count();
    }

    public function statisticsFor(Shop $shop): array
    {
        $totals = DB::table('referrals')
            ->where('shop_id', $shop->id)
            ->selectRaw("
                COUNT(*) as total_referrals,
                SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) as clicked_count,
                SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as registered_count,
                SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified_count,
                SUM(CASE WHEN status = 'rewarded' THEN 1 ELSE 0 END) as rewarded_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN fraud_status = 'flagged' THEN 1 ELSE 0 END) as flagged_count,
                COUNT(DISTINCT referrer_customer_id) as unique_referrers
            ")
            ->first();

        $registeredOrBetter = (int) DB::table('referrals')
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['registered', 'qualified', 'rewarded'])
            ->count();

        $rewardedCount = (int) ($totals->rewarded_count ?? 0);

        return [
            'total_referrals' => (int) ($totals->total_referrals ?? 0),
            'clicked_count' => (int) ($totals->clicked_count ?? 0),
            'registered_count' => (int) ($totals->registered_count ?? 0),
            'qualified_count' => (int) ($totals->qualified_count ?? 0),
            'rewarded_count' => $rewardedCount,
            'rejected_count' => (int) ($totals->rejected_count ?? 0),
            'flagged_count' => (int) ($totals->flagged_count ?? 0),
            'unique_referrers' => (int) ($totals->unique_referrers ?? 0),
            // Conversion rate: of everyone who registered via a
            // referral, what fraction went on to be rewarded — a more
            // meaningful "did this program work" number than raw counts.
            'registration_to_reward_rate' => $registeredOrBetter > 0 ? round($rewardedCount / $registeredOrBetter, 4) : 0.0,
        ];
    }
}
