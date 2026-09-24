<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated shop from the verified session token
 * (Shopify App Bridge session token, validated as a JWT by
 * VerifyShopifySessionToken earlier in the stack) and binds it to
 * TenantContext for the remainder of the request lifecycle.
 *
 * Deliberately does NOT check `is_installed` here — tenant
 * *identification* (which shop is this?) and *active shop validation*
 * (is this shop currently allowed to use the app?) are kept as two
 * separate middleware (see EnsureShopIsActive, next in the chain) so
 * each has one job and each is independently testable.
 */
class ResolveShopFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->attributes->get('shopify_domain');

        if (! $domain) {
            return response()->json(['message' => 'Unable to resolve shop from session.'], 401);
        }

        $shop = Shop::query()->where('shopify_domain', $domain)->first();

        if (! $shop) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        TenantContext::set($shop);
        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
