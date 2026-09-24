<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionAccessService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This app has no free plan. Every API route (aside from billing/auth
 * routes themselves) requires an active or trialing subscription. The
 * boolean check is centralized in
 * App\Services\Billing\SubscriptionAccessService (shared with
 * EnsureShopIsActive and the non-HTTP assertFullAccess() path) — this
 * middleware only owns turning "false" into the right HTTP response.
 */
class RequireActiveSubscription
{
    public function __construct(private readonly SubscriptionAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shop = TenantContext::shop();

        if (! $this->access->hasActiveSubscription($shop)) {
            return response()->json([
                'message' => 'An active subscription is required to use this app.',
                'error_code' => 'SUBSCRIPTION_REQUIRED',
                'billing_url' => route('billing.plans'),
            ], 402);
        }

        return $next($request);
    }
}
