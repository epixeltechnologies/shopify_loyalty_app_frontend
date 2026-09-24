<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Authentication for this app is entirely handled by
 * VerifyShopifySessionToken + ResolveShopFromSession middleware (see
 * routes/api.php) — there is no username/password or Sanctum login flow
 * for merchants. This endpoint simply confirms the resolved session,
 * which the frontend calls once on boot to distinguish "no/invalid
 * session" from "valid session, no subscription".
 */
class SessionController extends Controller
{
    public function me(): JsonResponse
    {
        $shop = TenantContext::shop();

        return response()->json([
            'data' => [
                'shop_domain' => $shop->shopify_domain,
                'onboarding_completed' => $shop->onboarding_completed,
            ],
        ]);
    }
}
