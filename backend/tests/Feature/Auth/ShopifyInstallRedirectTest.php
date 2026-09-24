<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShopifyInstallRedirectTest extends TestCase
{
    public function test_a_valid_shop_domain_redirects_to_shopify_authorization(): void
    {
        $response = $this->get('/auth?shop=my-store.myshopify.com');

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://my-store.myshopify.com/admin/oauth/authorize?', $location);
        $this->assertStringContainsString('client_id='.config('shopify.api_key'), $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_the_generated_state_is_stored_for_later_validation(): void
    {
        $response = $this->get('/auth?shop=my-store.myshopify.com');

        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertNotNull(Cache::get('shopify_oauth_state:my-store.myshopify.com'));
        $this->assertSame(Cache::get('shopify_oauth_state:my-store.myshopify.com'), $query['state']);
    }

    /** @dataProvider invalidShopProvider */
    public function test_an_invalid_shop_domain_is_rejected(string $shop): void
    {
        $this->getJson('/auth?shop='.urlencode($shop))->assertStatus(422);
    }

    public static function invalidShopProvider(): array
    {
        return [
            'not a shopify domain' => ['attacker.com'],
            'suffix spoofing' => ['my-store.myshopify.com.attacker.com'],
            'empty' => [''],
        ];
    }

    public function test_a_missing_shop_parameter_is_rejected(): void
    {
        $this->getJson('/auth')->assertStatus(422);
    }
}
