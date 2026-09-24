<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    public function show(): JsonResponse
    {
        $shop = TenantContext::shop();

        return response()->json([
            'data' => [
                'domain' => $shop->shopify_domain,
                'name' => $shop->name,
                'onboarding_completed' => $shop->onboarding_completed,
                'has_active_subscription' => $shop->entitlements()->hasActiveSubscription(),
            ],
        ]);
    }
}
