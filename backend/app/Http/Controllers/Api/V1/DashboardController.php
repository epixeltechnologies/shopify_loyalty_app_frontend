<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Placeholder foundation endpoint — actual loyalty metrics (points issued,
 * active members, redemption rate) land once the loyalty engine ships.
 */
class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $shop = TenantContext::shop();
        $entitlements = $shop->entitlements();

        return response()->json([
            'data' => [
                'plan' => $shop->activeSubscription?->plan->name,
                'customer_slots_remaining' => $entitlements->remainingCustomerSlots(),
                'available_vip_tiers' => $entitlements->availableVipTiers(),
                'has_advanced_analytics' => $entitlements->has('analytics.advanced'),
            ],
        ]);
    }
}
