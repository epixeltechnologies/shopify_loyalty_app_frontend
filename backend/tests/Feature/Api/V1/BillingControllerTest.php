<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActingAsShop;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use ActingAsShop, RefreshDatabase;

    public function test_billing_status_is_reachable_without_an_active_subscription(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->getJson('/api/v1/billing/status')
            ->assertOk()
            ->assertJson(['has_active_subscription' => false]);
    }

    public function test_plans_endpoint_returns_active_plans(): void
    {
        $shop = Shop::factory()->create();
        Plan::factory()->starter()->create();
        Plan::factory()->create(['is_active' => false]); // excluded

        $this->actingAsShop($shop)
            ->getJson('/api/v1/billing/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_dashboard_is_blocked_without_an_active_subscription(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->getJson('/api/v1/dashboard')
            ->assertStatus(402)
            ->assertJson(['error_code' => 'SUBSCRIPTION_REQUIRED']);
    }

    public function test_dashboard_is_reachable_with_an_active_starter_subscription(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/dashboard')
            ->assertOk();
    }

    public function test_dashboard_is_reachable_with_an_active_professional_subscription(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->professional()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $response = $this->actingAsShop($shop)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('Professional Plan', $response->json('data.plan'));
    }

    /** @dataProvider inactiveStatusProvider */
    public function test_dashboard_is_blocked_for_every_inactive_subscription_status(string $status): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => $status]);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/dashboard')
            ->assertStatus(402)
            ->assertJson(['error_code' => 'SUBSCRIPTION_REQUIRED']);
    }

    public static function inactiveStatusProvider(): array
    {
        return [
            'cancelled' => ['cancelled'],
            'declined' => ['declined'],
            'frozen' => ['frozen'],
            'expired' => ['expired'],
            'past_due' => ['past_due'],
            'pending' => ['pending'],
        ];
    }

    public function test_trialing_counts_as_active(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'trialing']);

        $this->actingAsShop($shop)->getJson('/api/v1/dashboard')->assertOk();
    }

    public function test_billing_status_still_reachable_for_an_uninstalled_shop_is_blocked_at_a_higher_layer(): void
    {
        // EnsureShopIsActive (shop.active middleware) runs before subscription
        // checks — an uninstalled shop is rejected with 410 regardless of
        // what its subscription state was.
        $shop = Shop::factory()->uninstalled()->create();

        $this->actingAsShop($shop)
            ->getJson('/api/v1/billing/status')
            ->assertStatus(410)
            ->assertJson(['error_code' => 'SHOP_UNINSTALLED']);
    }

    public function test_subscription_endpoint_returns_current_plan_and_dates(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $response = $this->actingAsShop($shop)->getJson('/api/v1/billing/subscription')->assertOk();

        $this->assertSame('active', $response->json('data.status'));
        $this->assertSame('Starter Plan', $response->json('data.plan.name'));
        $this->assertNotNull($response->json('data.current_period_end'));
    }

    public function test_usage_endpoint_returns_used_and_limit_for_each_metric(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);
        Customer::factory()->for($shop)->count(3)->create();
        SubscriptionUsage::query()->create(['shop_id' => $shop->id, 'metric' => SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 'value' => 3]);

        $response = $this->actingAsShop($shop)->getJson('/api/v1/billing/usage')->assertOk();

        $this->assertSame(3, $response->json('data.active_customers.used'));
        $this->assertSame(500, $response->json('data.active_customers.limit'));
    }

    public function test_downgrade_check_endpoint_reports_blockers(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        $starter = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);
        SubscriptionUsage::query()->create(['shop_id' => $shop->id, 'metric' => SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 'value' => 600]);

        $response = $this->actingAsShop($shop)
            ->getJson('/api/v1/billing/downgrade-check?plan=starter')
            ->assertOk();

        $this->assertFalse($response->json('eligible'));
        $this->assertNotEmpty($response->json('blockers'));
    }

    public function test_subscribe_to_a_blocked_downgrade_returns_422(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create(['shopify_plan_handle' => 'professional-handle']);
        $starter = Plan::factory()->starter()->create(['shopify_plan_handle' => 'starter-handle']);
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);
        SubscriptionUsage::query()->create(['shop_id' => $shop->id, 'metric' => SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 'value' => 600]);

        $response = $this->actingAsShop($shop)
            ->postJson('/api/v1/billing/subscribe', ['plan' => 'starter'])
            ->assertStatus(422);

        $this->assertSame('DOWNGRADE_BLOCKED', $response->json('error_code'));
        $this->assertNotEmpty($response->json('blockers'));
    }

    public function test_a_shop_cannot_see_another_shops_subscription_or_usage(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $planB = Plan::factory()->professional()->create();
        Subscription::factory()->for($shopB)->for($planB)->create(['status' => 'active']);

        $response = $this->actingAsShop($shopA)->getJson('/api/v1/billing/subscription')->assertOk();

        $this->assertNull($response->json('data.status'));
        $this->assertNotSame('Professional Plan', $response->json('data.plan.name'));
    }

    public function test_request_without_a_session_token_is_rejected(): void
    {
        $this->getJson('/api/v1/shop')
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }
}
