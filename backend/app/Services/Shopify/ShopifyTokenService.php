<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Support\Str;

/**
 * Single, dedicated place that ever reads or writes `shops.access_token`.
 * Everything else in the app (BillingService, ShopifyGraphQLClient, any
 * future service) should go through here rather than touching
 * `$shop->access_token` directly — this is what makes "never log a
 * token" and "never expose a token to an API response" enforceable in
 * one place instead of an audit-every-call-site problem.
 *
 * Storage: `access_token` uses Eloquent's `encrypted` cast
 * (App\Models\Shop) — AES-256-CBC via `APP_KEY`, Laravel's standard
 * encryption-at-rest primitive. This service does not add a second
 * encryption layer on top; it exists for redaction/access-control
 * semantics, not additional cryptography.
 *
 * Offline vs. online tokens: this app requests Shopify's OFFLINE access
 * token (the default for the authorization-code grant used in
 * ShopifyOAuthService — no `grant_options[]=per-user` parameter is
 * sent). Offline tokens don't expire and aren't tied to a specific
 * staff member's Shopify login, which is what long-lived background
 * work (webhook processing, scheduled jobs) requires — see
 * docs/AUTHENTICATION.md for the full comparison with the App Bridge
 * session token used for interactive requests.
 */
class ShopifyTokenService
{
    public function store(Shop $shop, string $accessToken, ?string $scopes): Shop
    {
        $shop->update([
            'access_token' => $accessToken,
            'scopes' => $scopes,
        ]);

        return $shop->fresh();
    }

    public function revoke(Shop $shop): Shop
    {
        $shop->update(['access_token' => null]);

        return $shop->fresh();
    }

    public function hasToken(Shop $shop): bool
    {
        return filled($shop->access_token);
    }

    /**
     * Retrieves the token for making an authenticated Admin API call.
     * Named distinctly from a plain property access so a `grep` for
     * "accessToken" in application code (as opposed to model internals)
     * turns up every real usage site.
     */
    public function accessTokenFor(Shop $shop): ?string
    {
        return $shop->access_token;
    }

    /**
     * A stable, non-reversible fingerprint safe to put in logs/metrics
     * when a token needs to be *referenced* (e.g. "token rotated for
     * shop X") without ever printing the token itself.
     */
    public function fingerprint(Shop $shop): ?string
    {
        return $shop->access_token ? Str::substr(hash('sha256', $shop->access_token), 0, 12) : null;
    }
}
