<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level feature gate: Route::middleware('feature:analytics.advanced').
 * Keeps controllers free of plan-checking conditionals.
 */
class EnsureFeatureEntitlement
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        if (! TenantContext::shop()->entitlements()->has($featureKey)) {
            return response()->json([
                'message' => 'Your current plan does not include this feature.',
                'error_code' => 'FEATURE_NOT_AVAILABLE',
                'feature' => $featureKey,
            ], 403);
        }

        return $next($request);
    }
}
