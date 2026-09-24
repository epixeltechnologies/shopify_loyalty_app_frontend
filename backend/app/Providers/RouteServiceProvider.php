<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->attributes->get('shop')?->id ?: $request->ip());
        });

        // Manual points adjustments and billing subscription changes are
        // both audited AND rate-limited tighter than general reads —
        // they mutate money-adjacent state and are attractive targets
        // for a compromised staff session to abuse quickly.
        RateLimiter::for('sensitive-writes', function (Request $request) {
            return Limit::perMinute(10)->by($request->attributes->get('shop')?->id ?: $request->ip());
        });

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));

        // Storefront (customer-facing) requests — keyed by shop+customer.
        // Ordered AFTER verify.shopify.app_proxy/storefront.customer in
        // the route group (see routes/api.php), so `shop`/
        // `storefront_customer` are already resolved by the time this
        // runs — an invalid-signature request is rejected immediately
        // and never consumes a legitimate customer's rate-limit budget.
        // A tighter per-minute ceiling than the merchant-admin 'api'
        // limiter above: a storefront widget is a handful of reads per
        // page load, never a bulk admin operation, and this surface is
        // reachable by anyone visiting the storefront rather than an
        // authenticated merchant staff session.
        RateLimiter::for('storefront', function (Request $request) {
            $shop = $request->attributes->get('shop');
            $customer = $request->attributes->get('storefront_customer');
            $key = $shop ? $shop->id.':'.($customer?->id ?? $request->ip()) : $request->ip();

            return Limit::perMinute(60)->by($key);
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('webhooks')
                ->group(base_path('routes/webhooks.php'));

            // No 'web'/'api' middleware group — a load balancer or
            // uptime monitor carries no session/CSRF context, and these
            // must stay fast and dependency-free of anything but the
            // base global middleware stack. See HealthController's
            // docblock and docs/MONITORING.md#health-checks.
            Route::prefix('health')
                ->group(base_path('routes/health.php'));
        });
    }
}
