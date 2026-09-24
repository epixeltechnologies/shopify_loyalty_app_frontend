<?php

namespace App\Http\Middleware;

use App\Exceptions\Shopify\InvalidShopDomainException;
use App\Models\Shop;
use App\Services\Shopify\ShopDomainValidator;
use App\Services\Shopify\ShopifyAppProxyVerifier;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STOREFRONT AUTH: the tenant half of storefront request verification
 * — see ShopifyAppProxyVerifier's docblock for the full rationale.
 * Verifies the app-proxy query signature (proving Shopify itself
 * produced this request for a specific shop), resolves that shop
 * through the SAME `ShopDomainValidator` the OAuth/webhook paths use
 * (no second, parallel domain-validation implementation), and binds
 * TenantContext — exactly like ResolveShopFromSession does for the
 * merchant-session path, just via a completely different verification
 * mechanism appropriate to an unauthenticated storefront visitor.
 *
 * Does NOT resolve which customer this is — see
 * ResolveStorefrontCustomer, next in the middleware stack, which reads
 * `logged_in_customer_id` (also verified by this same signature) to
 * answer that separately.
 */
class VerifyShopifyAppProxyRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(ShopifyAppProxyVerifier::class)->isValid($request->query())) {
            return response()->json(['message' => 'Invalid storefront request signature.'], 401);
        }

        try {
            $domain = app(ShopDomainValidator::class)->validateAndNormalize($request->query('shop'));
        } catch (InvalidShopDomainException) {
            return response()->json(['message' => 'Invalid shop.'], 400);
        }

        $shop = Shop::query()->where('shopify_domain', $domain)->first();

        if (! $shop || ! $shop->is_installed) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        TenantContext::set($shop);
        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
