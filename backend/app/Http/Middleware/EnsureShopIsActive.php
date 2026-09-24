<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\Billing\SubscriptionAccessService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Active shop validation" — runs immediately after tenant resolution
 * (ResolveShopFromSession) and rejects any request for a shop that has
 * uninstalled the app. Kept separate from tenant *identification* so a
 * 410 (a real, previously-installed shop that's gone) is distinguishable
 * from a 404 (no such shop at all) in logs, metrics, and tests. The
 * boolean check itself is centralized in
 * App\Services\Billing\SubscriptionAccessService — this middleware only
 * owns turning "false" into the right HTTP response.
 *
 * Also the natural place to record activity: every request that gets
 * this far is, by definition, an authenticated request from an active
 * shop, so this is where `last_activity_at` is updated — throttled to
 * once per 5 minutes per shop so a busy shop doesn't generate a DB write
 * on every single API call.
 */
class EnsureShopIsActive
{
    private const ACTIVITY_UPDATE_THROTTLE_MINUTES = 5;

    public function __construct(private readonly SubscriptionAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shop = TenantContext::shop();

        if (! $this->access->isShopActive($shop)) {
            return response()->json([
                'message' => 'This app is no longer installed on this store.',
                'error_code' => 'SHOP_UNINSTALLED',
            ], 410);
        }

        $this->recordActivity($shop);

        return $next($request);
    }

    private function recordActivity(Shop $shop): void
    {
        if (! $shop->last_activity_at || $shop->last_activity_at->diffInMinutes(now()) >= self::ACTIVITY_UPDATE_THROTTLE_MINUTES) {
            // A raw query update (not $shop->update()) deliberately: this
            // is a passive activity ping, not a meaningful change, so it
            // must not bump `updated_at` or mutate the shared $shop
            // instance other request-lifecycle code (TenantContext) is
            // still holding a reference to.
            DB::table('shops')->where('id', $shop->id)->update(['last_activity_at' => now()]);
        }
    }
}
