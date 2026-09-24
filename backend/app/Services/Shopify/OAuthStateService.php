<?php

namespace App\Services\Shopify;

use App\Exceptions\Shopify\OAuthStateException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates, stores, and single-use-validates the OAuth `state`
 * parameter (Shopify's CSRF-protection mechanism for the install flow).
 *
 * Security properties this class is responsible for:
 *   - **Secure generation**: 40 bytes of `Str::random()` (CSPRNG-backed),
 *     not a predictable value derived from the shop domain or timestamp.
 *   - **Expiration**: stored in cache with a TTL
 *     (`config('shopify.oauth.state_ttl')`) — a state value is
 *     worthless (and rejected) after that window, bounding how long an
 *     intercepted-but-unused authorize URL stays exploitable.
 *   - **Replay protection**: `consume()` reads AND deletes the cached
 *     value in one call (`Cache::pull`), so a given state can be
 *     successfully validated exactly once. A second callback replaying
 *     the same `state` (e.g. a captured URL replayed by an attacker,
 *     or Shopify/a browser retrying the callback) fails validation
 *     even within the TTL window.
 *   - **Per-shop scoping**: keyed by shop domain, so state generated
 *     for shop A can never validate a callback claiming to be shop B.
 */
class OAuthStateService
{
    public function generate(string $shopDomain): string
    {
        $state = Str::random(40);

        Cache::put($this->cacheKey($shopDomain), $state, config('shopify.oauth.state_ttl', 600));

        return $state;
    }

    /**
     * @throws OAuthStateException if the state is missing/expired/replayed, or doesn't match.
     */
    public function consume(string $shopDomain, ?string $providedState): void
    {
        if (! $providedState) {
            Log::channel('security')->warning('oauth_state_missing', ['shop' => $shopDomain]);
            throw OAuthStateException::mismatch();
        }

        $storedState = Cache::pull($this->cacheKey($shopDomain));

        if (! $storedState) {
            // Either the state expired (TTL passed) or this exact state
            // was already consumed once — the two look identical from
            // here since consume() deletes on read, which is exactly
            // the point: never log the state value itself, only that a
            // callback for this shop failed replay/expiry validation.
            Log::channel('security')->warning('oauth_state_expired_or_replayed', ['shop' => $shopDomain]);
            throw OAuthStateException::expiredOrMissing();
        }

        if (! hash_equals($storedState, $providedState)) {
            Log::channel('security')->warning('oauth_state_mismatch', ['shop' => $shopDomain]);
            throw OAuthStateException::mismatch();
        }
    }

    private function cacheKey(string $shopDomain): string
    {
        return "shopify_oauth_state:{$shopDomain}";
    }
}
