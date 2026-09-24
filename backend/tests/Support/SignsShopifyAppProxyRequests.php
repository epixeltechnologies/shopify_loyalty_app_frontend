<?php

namespace Tests\Support;

/**
 * Shared helper for every storefront test — builds a real, correctly-
 * signed app-proxy query string so tests exercise the actual
 * VerifyShopifyAppProxyRequest middleware rather than bypassing it.
 * Mirrors SignsShopifyWebhooks' role for the webhook pipeline.
 */
trait SignsShopifyAppProxyRequests
{
    /**
     * @param  array<string, string>  $params  e.g. ['shop' => '...', 'logged_in_customer_id' => '123']
     * @return string the full query string, including a valid `signature`
     */
    private function signedAppProxyQuery(array $params): string
    {
        ksort($params);

        $canonical = '';
        foreach ($params as $key => $value) {
            $canonical .= "{$key}={$value}";
        }

        $signature = hash_hmac('sha256', $canonical, config('shopify.api_secret'));

        return http_build_query([...$params, 'signature' => $signature]);
    }
}
