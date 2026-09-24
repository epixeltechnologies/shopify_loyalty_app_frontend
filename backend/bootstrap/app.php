<?php

use App\Exceptions\Handlers\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureFeatureEntitlement;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureShopIsActive;
use App\Http\Middleware\LogApiRequests;
use App\Http\Middleware\RequireActiveSubscription;
use App\Http\Middleware\ResolveShopFromSession;
use App\Http\Middleware\ResolveStorefrontCustomer;
use App\Http\Middleware\VerifyShopifyAppProxyRequest;
use App\Http\Middleware\VerifyShopifySessionToken;
use App\Http\Middleware\VerifyShopifyWebhook;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // SECURITY FIX (hardening audit): RateLimiter::for('api', ...) was
        // already defined in RouteServiceProvider, but nothing actually
        // applied it — Laravel's `api` middleware GROUP (configured here)
        // and the `api` RATE LIMITER (a same-named but separate concept)
        // don't wire together automatically unless `throttle:api` is
        // explicitly added to the group. Without this, the entire
        // merchant-admin API surface had NO rate limiting at all except
        // the handful of routes explicitly tagged with
        // throttle:sensitive-writes/storefront/webhooks — every other
        // endpoint (customer lists, points, rewards, VIP tiers, settings,
        // analytics reports, ...) was unlimited.
        //
        // NOTE: at this point in the stack, tenant resolution
        // (verify.shopify.session/tenant.resolve) hasn't run yet — those
        // are per-route-group middleware applied later, inside
        // routes/api.php, not at this global level. So `$request->attributes->get('shop')`
        // is always null here, and RateLimiter::for('api')'s callback
        // falls back to IP-based keying for the merchant-admin surface in
        // practice (it IS shop-scoped for the storefront limiter, which
        // runs after its own tenant-resolution middleware — see
        // RouteServiceProvider). IP-based limiting at the global level is
        // still a real, meaningful improvement over no limiting at all;
        // achieving precise per-shop keying here would require
        // restructuring middleware order, a larger change than this
        // hardening pass warrants — see the security report's "remaining
        // risks" section.
        $middleware->api(prepend: [
            AssignRequestId::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->api(append: [
            LogApiRequests::class,
        ]);

        $middleware->alias([
            'verify.shopify.session' => VerifyShopifySessionToken::class,
            'verify.shopify.webhook' => VerifyShopifyWebhook::class,
            'tenant.resolve' => ResolveShopFromSession::class,
            'shop.active' => EnsureShopIsActive::class,
            'subscription.active' => RequireActiveSubscription::class,
            'feature' => EnsureFeatureEntitlement::class,
            'permission' => EnsurePermission::class,
            'verify.shopify.app_proxy' => VerifyShopifyAppProxyRequest::class,
            'storefront.customer' => ResolveStorefrontCustomer::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());

        // Every uncaught exception on an API request is rendered through
        // one place — see ApiExceptionRenderer's docblock for why this
        // is also the intended "error tracking" integration point.
        $exceptions->render(fn (Throwable $e, $request) => app(ApiExceptionRenderer::class)->render($e, $request));

        // Reportable hook: routed through App\Services\ErrorTracking\ErrorTracker
        // (see docs/MONITORING.md#error-tracking) — today this always
        // logs (LogErrorTracker, the default binding), but is the one
        // seam a real provider (Sentry, etc.) plugs into later without
        // touching this file or any business code again.
        $exceptions->reportable(function (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return; // expected, already logged at 'warning' by ApiExceptionRenderer
            }

            $request = request();

            app(\App\Services\ErrorTracking\ErrorTracker::class)->captureException($e, [
                'request_id' => $request?->attributes->get('request_id'),
                'path' => $request?->path(),
                'shop_id' => $request?->attributes->get('shop')?->id,
            ]);
        });
    })
    ->create();
