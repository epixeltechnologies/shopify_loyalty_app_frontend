<?php

namespace App\Http\Controllers\Api\V1\VipTiers;

use App\Support\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** The "VIP Overview" page's analytics summary — customer counts per tier and recent upgrade/downgrade activity. */
class VipStatisticsController extends Controller
{
    public function index(): JsonResponse
    {
        $shop = TenantContext::shop();

        $perTier = DB::table('vip_tiers')
            ->leftJoin('customers', function ($join) use ($shop) {
                $join->on('customers.vip_tier_id', '=', 'vip_tiers.id')->where('customers.shop_id', $shop->id);
            })
            ->where('vip_tiers.shop_id', $shop->id)
            ->groupBy('vip_tiers.id', 'vip_tiers.name', 'vip_tiers.sort_order')
            ->orderBy('vip_tiers.sort_order')
            ->selectRaw('vip_tiers.id, vip_tiers.name, vip_tiers.sort_order, COUNT(customers.id) as customer_count')
            ->get();

        $recentActivity = DB::table('customer_vip_history')
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("
                SUM(CASE WHEN direction = 'upgrade' THEN 1 ELSE 0 END) as upgrades_last_30_days,
                SUM(CASE WHEN direction = 'downgrade' THEN 1 ELSE 0 END) as downgrades_last_30_days
            ")
            ->first();

        return response()->json(['data' => [
            'tiers' => $perTier,
            'upgrades_last_30_days' => (int) ($recentActivity->upgrades_last_30_days ?? 0),
            'downgrades_last_30_days' => (int) ($recentActivity->downgrades_last_30_days ?? 0),
        ]]);
    }
}
