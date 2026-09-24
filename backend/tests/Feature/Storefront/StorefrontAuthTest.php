<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsShopifyAppProxyRequests;
use Tests\TestCase;

class StorefrontAuthTest extends TestCase
{
    use RefreshDatabase, SignsShopifyAppProxyRequests;

    private function subscribedShop(): Shop
    {
        $shop = Shop::factory()->create();
        Subscription::factory()->for($shop)->for(Plan::factory()->starter()->create())->create(['status' => 'active']);

        return $shop;
    }

    public function test_a_correctly_signed_request_with_a_logged_in_customer_succeeds(): void
    {
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '555']);

        $query = $this->signedAppProxyQuery(['shop' => $shop->shopify_domain, 'logged_in_customer_id' => '555']);

        $this->getJson("/api/v1/storefront/profile?{$query}")->assertOk();
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $shop = $this->subscribedShop();

        $this->getJson("/api/v1/storefront/profile?shop={$shop->shopify_domain}&logged_in_customer_id=555&signature=not-a-real-signature")
            ->assertStatus(401);
    }

    public function test_a_tampered_shop_parameter_is_rejected(): void
    {
        $shop = $this->subscribedShop();
        $query = $this->signedAppProxyQuery(['shop' => $shop->shopify_domain, 'logged_in_customer_id' => '555']);
        $tamperedQuery = str_replace($shop->shopify_domain, 'attacker-shop.myshopify.com', $query);

        $this->getJson("/api/v1/storefront/profile?{$tamperedQuery}")->assertStatus(401);
    }

    public function test_a_request_with_no_logged_in_customer_is_rejected(): void
    {
        $shop = $this->subscribedShop();
        $query = $this->signedAppProxyQuery(['shop' => $shop->shopify_domain]);

        $this->getJson("/api/v1/storefront/profile?{$query}")->assertStatus(401);
    }

    public function test_a_shopify_customer_with_no_local_record_is_auto_enrolled(): void
    {
        $shop = $this->subscribedShop();
        $query = $this->signedAppProxyQuery(['shop' => $shop->shopify_domain, 'logged_in_customer_id' => '999']);

        $this->getJson("/api/v1/storefront/profile?{$query}")->assertOk();

        $this->assertDatabaseHas('customers', ['shop_id' => $shop->id, 'shopify_customer_id' => '999']);
    }

    public function test_a_second_request_for_the_same_customer_does_not_create_a_duplicate(): void
    {
        $shop = $this->subscribedShop();
        $query = $this->signedAppProxyQuery(['shop' => $shop->shopify_domain, 'logged_in_customer_id' => '999']);

        $this->getJson("/api/v1/storefront/profile?{$query}")->assertOk();
        $this->getJson("/api/v1/storefront/profile?{$query}")->assertOk();

        $this->assertSame(1, Customer::query()->where('shop_id', $shop->id)->where('shopify_customer_id', '999')->count());
    }

    public function test_an_uninstalled_shop_is_rejected(): void
    {
        $shop = Shop::factory()->create(['is_installed' => false]);
        $query = $this->signedAppProxyQuery(['shop' => $shop->shopify_domain, 'logged_in_customer_id' => '555']);

        $this->getJson("/api/v1/storefront/profile?{$query}")->assertStatus(404);
    }

    public function test_a_shop_with_no_active_subscription_is_rejected(): void
    {
        $shop = Shop::factory()->create(); // no subscription
        $query = $this->signedAppProxyQuery(['shop' => $shop->shopify_domain, 'logged_in_customer_id' => '555']);

        $this->getJson("/api/v1/storefront/profile?{$query}")->assertStatus(402);
    }
}
