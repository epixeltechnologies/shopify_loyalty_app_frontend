<?php

namespace App\Services\Shopify;

use App\Events\Shop\ShopInstalled;
use App\Exceptions\Shopify\InvalidHmacException;
use App\Exceptions\Shopify\TokenExchangeException;
use App\Jobs\Shopify\RegisterShopWebhooksJob;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Repositories\Contracts\ShopRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implements Shopify's OAuth "authorization code grant" flow end to end:
 * authorize-URL construction, callback validation (HMAC + state), token
 * exchange, and shop provisioning. See docs/AUTHENTICATION.md for how
 * this fits alongside App Bridge session tokens and what "merchant
 * authentication" means in this app.
 *
 * Deliberately requests an OFFLINE access token (no
 * `grant_options[]=per-user` in the authorize URL) — this app needs
 * long-lived Admin API access for background jobs (webhook processing,
 * scheduled analytics rollups) that run with no merchant present to
 * hold an online session. See ShopifyTokenService's docblock for the
 * offline-vs-online distinction in full.
 */
class ShopifyOAuthService
{
    public function __construct(
        private readonly ShopRepositoryInterface $shops,
        private readonly ShopDomainValidator $domainValidator,
        private readonly OAuthStateService $state,
        private readonly ShopifyTokenService $tokens,
    ) {}

    /**
     * Step 1 of the flow: build the URL the merchant is redirected to
     * for Shopify's own authorization screen. The shop domain is
     * validated/normalized here — before it's ever interpolated into an
     * outbound URL — which is what prevents SSRF/host-header attacks via
     * a malicious `?shop=` value (see ShopDomainValidator's docblock).
     */
    public function buildAuthorizeUrl(string $rawShopDomain): string
    {
        $shopDomain = $this->domainValidator->validateAndNormalize($rawShopDomain);
        $state = $this->state->generate($shopDomain);

        $params = http_build_query([
            'client_id' => config('shopify.api_key'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => config('app.url').config('shopify.oauth.callback_path'),
            'state' => $state,
        ]);

        return "https://{$shopDomain}/admin/oauth/authorize?{$params}";
    }

    /**
     * Step 2: handle the callback Shopify redirects back to after the
     * merchant approves the install. Every validation happens BEFORE
     * the token-exchange network call, in order of cheapest-to-check
     * first, so a malformed/malicious request is rejected without ever
     * reaching Shopify's servers or touching the database:
     *
     *   1. shop domain format (ShopDomainValidator)
     *   2. HMAC over the full callback query string
     *   3. OAuth state (expiration + single-use replay protection)
     *   4. token exchange with Shopify
     *   5. response shape validation
     *
     * @throws \App\Exceptions\Shopify\InvalidShopDomainException
     * @throws InvalidHmacException
     * @throws \App\Exceptions\Shopify\OAuthStateException
     * @throws TokenExchangeException
     */
    public function handleCallback(string $rawShopDomain, string $code, string $hmac, array $queryParams): Shop
    {
        $shopDomain = $this->domainValidator->validateAndNormalize($rawShopDomain);

        $this->verifyHmac($queryParams, $hmac);
        $this->state->consume($shopDomain, $queryParams['state'] ?? null);

        $tokenPayload = $this->exchangeCodeForToken($shopDomain, $code);

        $existingShop = $this->shops->findByDomain($shopDomain);
        $isReinstall = $existingShop !== null;

        $shop = $existingShop ?? $this->shops->create(['shopify_domain' => $shopDomain]);

        $this->tokens->store($shop, $tokenPayload['access_token'], $tokenPayload['scope'] ?? null);

        $shop->update([
            'is_installed' => true,
            'installed_at' => $shop->installed_at ?? now(),
            'uninstalled_at' => null,
            'last_activity_at' => now(),
        ]);

        $shop = $shop->fresh();

        $this->initializeDefaults($shop);

        // Webhook (re-)registration and any other install-time work that
        // needs Admin API calls is queued rather than run inline, so the
        // callback response (the merchant's redirect into the embedded
        // app) isn't held up by ~10 sequential HTTP requests to Shopify.
        RegisterShopWebhooksJob::dispatch($shop);

        if (! $isReinstall) {
            event(new ShopInstalled($shop));
        }

        Log::channel('security')->info($isReinstall ? 'shop_reinstalled' : 'shop_installed', [
            'shop' => $shop->shopify_domain,
            'token_fingerprint' => $this->tokens->fingerprint($shop),
        ]);

        return $shop;
    }

    /**
     * Idempotent — safe to call for both a fresh install and a
     * reinstall. `ShopSetting` is 1:1, so `firstOrCreate` never
     * duplicates a settings row for a shop reinstalling with its
     * historical settings intact.
     */
    private function initializeDefaults(Shop $shop): void
    {
        ShopSetting::query()->firstOrCreate(['shop_id' => $shop->id]);
    }

    private function exchangeCodeForToken(string $shopDomain, string $code): array
    {
        $response = Http::asJson()->post("https://{$shopDomain}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'code' => $code,
        ]);

        if ($response->failed()) {
            Log::channel('security')->error('oauth_token_exchange_failed', ['shop' => $shopDomain, 'status' => $response->status()]);

            throw TokenExchangeException::requestFailed($response->status());
        }

        $body = $response->json();

        if (! is_array($body) || empty($body['access_token'])) {
            Log::channel('security')->error('oauth_token_exchange_malformed_response', ['shop' => $shopDomain]);

            throw TokenExchangeException::malformedResponse();
        }

        return $body;
    }

    private function verifyHmac(array $params, string $hmac): void
    {
        $data = collect($params)->except(['hmac', 'signature'])->sortKeys()
            ->map(fn ($v, $k) => "{$k}={$v}")->implode('&');

        $computed = hash_hmac('sha256', $data, config('shopify.api_secret'));

        if (! hash_equals($computed, $hmac)) {
            throw new InvalidHmacException;
        }
    }
}
