<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Repositories\Contracts\RewardRedemptionRepositoryInterface;
use Illuminate\Support\Facades\DB;

class RewardRedemptionRepository implements RewardRedemptionRepositoryInterface
{
    public function paginateForCustomer(Customer $customer, int $perPage = 25)
    {
        return RewardRedemption::query()
            ->where('customer_id', $customer->id)
            ->with('reward')
            ->latest()
            ->paginate($perPage);
    }

    public function paginateForShop(Shop $shop, int $perPage = 25)
    {
        return RewardRedemption::query()
            ->where('shop_id', $shop->id)
            ->with(['customer', 'reward'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes): RewardRedemption
    {
        return RewardRedemption::query()->create($attributes);
    }

    public function statisticsFor(Shop $shop): array
    {
        $totals = DB::table('reward_redemptions')
            ->where('shop_id', $shop->id)
            ->selectRaw("
                COUNT(*) as total_redemptions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE WHEN status = 'completed' THEN points_spent ELSE 0 END) as total_points_redeemed,
                COUNT(DISTINCT customer_id) as customers_who_redeemed
            ")
            ->first();

        return [
            'total_redemptions' => (int) ($totals->total_redemptions ?? 0),
            'completed_count' => (int) ($totals->completed_count ?? 0),
            'pending_count' => (int) ($totals->pending_count ?? 0),
            'failed_count' => (int) ($totals->failed_count ?? 0),
            'cancelled_count' => (int) ($totals->cancelled_count ?? 0),
            'total_points_redeemed' => (int) ($totals->total_points_redeemed ?? 0),
            'customers_who_redeemed' => (int) ($totals->customers_who_redeemed ?? 0),
        ];
    }
}
