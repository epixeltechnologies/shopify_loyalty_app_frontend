<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level in-app permission gate: Route::middleware('permission:points.adjust').
 * Distinct from `subscription.active`/`feature:{key}` (what the shop's
 * PLAN allows) and from tenant scoping (which SHOP's data is visible) —
 * this is the third axis: what the authenticated staff USER is allowed
 * to do. See docs/SECURITY.md#authorization.
 *
 * No route currently uses this middleware — permission enforcement is
 * foundation, wired and ready, but no staff-authentication flow exists
 * yet to populate `$request->user()` (this app currently authenticates
 * merchants via Shopify session token, not a staff login). Left in
 * place for the staff-accounts milestone (docs/NEXT_STEPS.md step 9).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasPermission($permission)) {
            return ApiResponse::error(
                message: 'You do not have permission to perform this action.',
                status: 403,
                errorCode: 'PERMISSION_DENIED',
            );
        }

        return $next($request);
    }
}
