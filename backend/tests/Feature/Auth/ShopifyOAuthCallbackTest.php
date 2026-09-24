<?php

namespace Tests\Feature\Auth;

use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\Webhook;
use App\Services\Shopify\OAuthStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mocks every Shopify API call via Http::fake() — no test in this suite
 * ever calls the real Shopify API. See docs/TESTING.md.
 */
class ShopifyOAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP_DOMAIN = 'test-shop.myshopify.com';

    private function fakeShopifyTokenExchange(array $overrides = []): void
    {
        Http::fake([
            "https://".self::SHOP_DOMAIN."/admin/oauth/access_token" => Http::response(array_merge([
                'access_token' => 'shpat_fake_token_value',
                'scope' => 'read_customers,write_customers',
            ], $overrides)),
            "https://".self::SHOP_DOMAIN."/admin/api/*/graphql.json" => Http::response([
                'data' => ['webhookSubscriptionCreate' => ['webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'], 'userErrors' => []]],
            ]),
        ]);
    }

    private function validQueryParams(string $state): array
    {
        $params = [
            'shop' => self::SHOP_DOMAIN,
            'code' => 'a-valid-looking-auth-code',
            'state' => $state,
            'timestamp' => (string) now()->timestamp,
        ];

        $data = collect($params)->sortKeys()->map(fn ($v, $k) => "{$k}={$v}")->implode('&');
        $params['hmac'] = hash_hmac('sha256', $data, config('shopify.api_secret'));

        return $params;
    }

    public function test_a_valid_callback_installs_the_shop(): void
    {
        $this->fakeShopifyTokenExchange();
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $response = $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)));

        $response->assertRedirect();
        $this->assertStringContainsString(self::SHOP_DOMAIN, $response->headers->get('Location'));

        $shop = Shop::query()->where('shopify_domain', self::SHOP_DOMAIN)->first();
        $this->assertNotNull($shop);
        $this->assertTrue($shop->is_installed);
        $this->assertSame('shpat_fake_token_value', $shop->access_token);
        $this->assertNotNull($shop->installed_at);
    }

    public function test_a_valid_callback_initializes_default_shop_settings(): void
    {
        $this->fakeShopifyTokenExchange();
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)));

        $shop = Shop::query()->where('shopify_domain', self::SHOP_DOMAIN)->first();
        $this->assertDatabaseHas('shop_settings', ['shop_id' => $shop->id]);
    }

    public function test_a_valid_callback_registers_webhooks(): void
    {
        $this->fakeShopifyTokenExchange();
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graphql.json')
            && str_contains($request->body(), 'webhookSubscriptionCreate'));
    }

    public function test_the_access_token_never_appears_in_the_redirect_response(): void
    {
        $this->fakeShopifyTokenExchange();
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $response = $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)));

        $response->assertHeaderMissing('X-Access-Token');
        $this->assertStringNotContainsString('shpat_fake_token_value', (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString('shpat_fake_token_value', $response->content() ?: '');
    }

    public function test_an_invalid_hmac_is_rejected(): void
    {
        $this->fakeShopifyTokenExchange();
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $params = $this->validQueryParams($state);
        $params['hmac'] = 'not-the-real-hmac-value';

        $this->get('/auth/callback?'.http_build_query($params))->assertStatus(401);

        $this->assertDatabaseMissing('shops', ['shopify_domain' => self::SHOP_DOMAIN]);
    }

    public function test_an_invalid_state_is_rejected(): void
    {
        $this->fakeShopifyTokenExchange();
        app(OAuthStateService::class)->generate(self::SHOP_DOMAIN); // generates a state, but we won't use it

        $params = $this->validQueryParams('a-totally-different-state-value');

        $this->get('/auth/callback?'.http_build_query($params))->assertStatus(401);
    }

    public function test_an_expired_state_is_rejected(): void
    {
        $this->fakeShopifyTokenExchange();
        // Simulate expiry by never generating a state for this shop at all —
        // OAuthStateServiceTest already covers the TTL mechanics directly;
        // here we confirm the callback endpoint surfaces it as a 401.
        Cache::forget('shopify_oauth_state:'.self::SHOP_DOMAIN);

        $params = $this->validQueryParams('some-state-that-was-never-stored');

        $this->get('/auth/callback?'.http_build_query($params))->assertStatus(401);
    }

    public function test_an_invalid_shop_domain_is_rejected_before_any_shopify_call(): void
    {
        Http::fake(); // no endpoints registered — any call would fail the test

        $params = [
            'shop' => 'attacker.com',
            'code' => 'code',
            'state' => 'state',
            'hmac' => 'hmac',
        ];

        // getJson() so the OAuthCallbackRequest validation failure (shop
        // domain regex) renders as JSON 422 rather than Laravel's default
        // redirect-back behavior for a plain web-route request without an
        // Accept header — see ApiExceptionRenderer's docblock on why it
        // only takes over for api/* paths or requests that expect JSON.
        $this->getJson('/auth/callback?'.http_build_query($params))->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_token_exchange_failure_is_handled_gracefully(): void
    {
        Http::fake([
            "https://".self::SHOP_DOMAIN."/admin/oauth/access_token" => Http::response(['error' => 'invalid_request'], 400),
        ]);
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)))
            ->assertStatus(502);

        $this->assertDatabaseMissing('shops', ['shopify_domain' => self::SHOP_DOMAIN]);
    }

    public function test_a_malformed_token_exchange_response_is_handled_gracefully(): void
    {
        Http::fake([
            "https://".self::SHOP_DOMAIN."/admin/oauth/access_token" => Http::response(['unexpected' => 'shape'], 200),
        ]);
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)))
            ->assertStatus(502);
    }

    public function test_reinstalling_an_existing_shop_updates_rather_than_duplicates(): void
    {
        $existingShop = Shop::factory()->create([
            'shopify_domain' => self::SHOP_DOMAIN,
            'is_installed' => false,
            'uninstalled_at' => now()->subDay(),
        ]);
        ShopSetting::factory()->create(['shop_id' => $existingShop->id, 'notification_email' => 'preserved@example.com']);

        $this->fakeShopifyTokenExchange();
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)));

        $this->assertSame(1, Shop::query()->where('shopify_domain', self::SHOP_DOMAIN)->count());

        $shop = Shop::query()->where('shopify_domain', self::SHOP_DOMAIN)->first();
        $this->assertTrue($shop->is_installed);
        $this->assertNull($shop->uninstalled_at);
        $this->assertSame($existingShop->id, $shop->id);

        // Historical shop settings are preserved across reinstall, not recreated.
        $this->assertDatabaseHas('shop_settings', ['shop_id' => $shop->id, 'notification_email' => 'preserved@example.com']);
    }

    public function test_duplicate_installation_attempts_never_create_two_shop_rows(): void
    {
        $this->fakeShopifyTokenExchange();

        $state1 = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);
        $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state1)));

        $state2 = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);
        $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state2)));

        $this->assertSame(1, Shop::query()->where('shopify_domain', self::SHOP_DOMAIN)->count());
    }

    public function test_shopify_api_failure_during_webhook_registration_does_not_fail_the_install(): void
    {
        Http::fake([
            "https://".self::SHOP_DOMAIN."/admin/oauth/access_token" => Http::response([
                'access_token' => 'shpat_fake_token_value',
                'scope' => 'read_customers',
            ]),
            "https://".self::SHOP_DOMAIN."/admin/api/*/graphql.json" => Http::response('Internal Server Error', 500),
        ]);
        $state = app(OAuthStateService::class)->generate(self::SHOP_DOMAIN);

        // The install itself must succeed even though the (queued, sync-run)
        // webhook registration job hits a failing Shopify API — a Shopify
        // outage during webhook setup should never block the merchant from
        // completing installation.
        $response = $this->get('/auth/callback?'.http_build_query($this->validQueryParams($state)));

        $response->assertRedirect();
        $this->assertDatabaseHas('shops', ['shopify_domain' => self::SHOP_DOMAIN, 'is_installed' => true]);
    }
}
