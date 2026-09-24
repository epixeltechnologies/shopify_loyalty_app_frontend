<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsShopifyAppProxyRequests;
use Tests\TestCase;

class StorefrontApiTest extends TestCase
{
    use RefreshDatabase, SignsShopifyAppProxyRequests;

    private function subscribedShop(): Shop
    {
        $shop = Shop::factory()->create();
        Subscription::factory()->for($shop)->for(Plan::factory()->starter()->create())->create(['status' => 'active']);

        return $shop;
    }

    private function query(Shop $shop, string $customerId): string
    {
        return $this->signedAppProxyQuery(['shop' => $shop->shopify_domain, 'logged_in_customer_id' => $customerId]);
    }

    public function test_the_profile_reflects_this_customers_real_points_balance(): void
    {
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '1', 'first_name' => 'Ada']);
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);

        $response = $this->getJson('/api/v1/storefront/profile?'.$this->query($shop, '1'))->assertOk();

        $response->assertJsonPath('data.points_balance', 500)
            ->assertJsonPath('data.first_name', 'Ada');
        $this->assertArrayNotHasKey('id', $response->json('data')); // never an internal database ID
    }

    public function test_a_customer_can_only_ever_see_their_own_points_never_another_customers(): void
    {
        $shop = $this->subscribedShop();
        $customerA = Customer::factory()->for($shop)->create(['shopify_customer_id' => '1']);
        $customerB = Customer::factory()->for($shop)->create(['shopify_customer_id' => '2']);
        app(PointsLedgerService::class)->post($customerA, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE);
        app(PointsLedgerService::class)->post($customerB, PointTransaction::DIRECTION_EARN, 9999, PointTransaction::SOURCE_PURCHASE);

        $response = $this->getJson('/api/v1/storefront/points/balance?'.$this->query($shop, '1'))->assertOk();

        $this->assertSame(100, $response->json('data.balance'));
    }

    public function test_reward_redemption_reuses_the_centralized_redemption_service_and_deducts_points(): void
    {
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '1']);
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 300, 'status' => 'active']);

        $response = $this->postJson("/api/v1/storefront/rewards/{$reward->id}/redeem?".$this->query($shop, '1'))
            ->assertStatus(201);

        $this->assertSame('pending', $response->json('data.status'));
        $this->assertSame(700, $customer->point->fresh()->balance);
    }

    public function test_redeeming_with_insufficient_points_fails_and_deducts_nothing(): void
    {
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '1']);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 5000, 'status' => 'active']);

        $this->postJson("/api/v1/storefront/rewards/{$reward->id}/redeem?".$this->query($shop, '1'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INSUFFICIENT_POINTS');

        $this->assertSame(0, $customer->point->fresh()->balance);
    }

    public function test_a_customer_cannot_redeem_a_reward_belonging_to_another_shop(): void
    {
        $shopA = $this->subscribedShop();
        $shopB = $this->subscribedShop();
        Customer::factory()->for($shopA)->create(['shopify_customer_id' => '1']);
        $rewardFromOtherShop = Reward::factory()->for($shopB)->create(['points_cost' => 10, 'status' => 'active']);

        $this->postJson("/api/v1/storefront/rewards/{$rewardFromOtherShop->id}/redeem?".$this->query($shopA, '1'))
            ->assertStatus(404);
    }

    public function test_referral_code_and_link_belong_to_the_authenticated_customer(): void
    {
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '1']);

        $response = $this->getJson('/api/v1/storefront/referral/code?'.$this->query($shop, '1'))->assertOk();

        $this->assertSame($customer->referral_code, $response->json('data.referral_code'));
    }

    public function test_vip_current_tier_reflects_the_authenticated_customer(): void
    {
        $shop = $this->subscribedShop();
        $tier = \App\Models\VipTier::factory()->for($shop)->create(['name' => 'Gold']);
        Customer::factory()->for($shop)->create(['shopify_customer_id' => '1', 'vip_tier_id' => $tier->id]);

        $response = $this->getJson('/api/v1/storefront/vip/current?'.$this->query($shop, '1'))->assertOk();

        $this->assertSame('Gold', $response->json('data.name'));
    }

    public function test_points_history_is_paginated_not_returned_in_full(): void
    {
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '1']);
        $ledger = app(PointsLedgerService::class);
        for ($i = 0; $i < 25; $i++) {
            $ledger->post($customer, PointTransaction::DIRECTION_EARN, 10, PointTransaction::SOURCE_PURCHASE);
        }

        $response = $this->getJson('/api/v1/storefront/points/history?'.$this->query($shop, '1'))->assertOk();

        $this->assertLessThan(25, count($response->json('data')));
    }
}
