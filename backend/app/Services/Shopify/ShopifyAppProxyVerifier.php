<?php

namespace App\Services\Shopify;

/**
 * STOREFRONT AUTH: verifies a Shopify App Proxy request's signature —
 * this IS "Shopify's current recommended customer authentication
 * approach" for a storefront-facing app surface. Shopify proxies
 * requests from `https://{shop}.myshopify.com/apps/{proxy-subpath}/*`
 * to this app's backend, appending `shop` and (when a customer is
 * genuinely logged into the storefront) `logged_in_customer_id` as
 * query params, then signs the WHOLE query string with the app's
 * shared secret. Verifying that signature server-side is what proves
 * a request — and any `logged_in_customer_id` it carries — truly came
 * from Shopify, without this app ever building its own customer
 * login/password flow or trusting a client-supplied customer ID.
 *
 * Shopify's app-proxy signing algorithm is deliberately DIFFERENT from
 * the OAuth/webhook HMAC schemes elsewhere in this app (see
 * VerifyShopifyWebhook, ShopifyOAuthService): parameters are sorted by
 * key and concatenated as `key=value` pairs with NO separator between
 * them (not `&`), not the query string's raw byte form.
 */
class ShopifyAppProxyVerifier
{
    public function __construct(private readonly string $apiSecret) {}

    /**
     * @param  array<string, string|array>  $queryParams  The full query string, including `signature`.
     */
    public function isValid(array $queryParams): bool
    {
        $signature = $queryParams['signature'] ?? null;
        if (! $signature || ! is_string($signature)) {
            return false;
        }

        unset($queryParams['signature']);

        ksort($queryParams);

        $canonical = '';
        foreach ($queryParams as $key => $value) {
            $flatValue = is_array($value) ? implode(',', $value) : (string) $value;
            $canonical .= "{$key}={$flatValue}";
        }

        $computed = hash_hmac('sha256', $canonical, $this->apiSecret);

        return hash_equals($computed, $signature);
    }
}
