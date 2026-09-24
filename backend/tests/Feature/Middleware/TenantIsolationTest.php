<?php

namespace Tests\Feature\Middleware;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActingAsShop;
use Tests\TestCase;

/**
 * Confirms tenant A can never see or affect tenant B's data — the core
 * guarantee of App\Models\Scopes\TenantScope (see docs/MULTI_TENANCY.md).
 */
class TenantIsolationTest extends TestCase
{
    use ActingAsShop, RefreshDatabase;

    private function subscribedShop(): Shop
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        return $shop;
    }

    public function test_a_shop_cannot_list_another_shops_customers(): void
    {
        $shopA = $this->subscribedShop();
        $shopB = $this->subscribedShop();

        Customer::factory()->for($shopB)->create(['email' => 'belongs-to-b@example.com']);

        $response = $this->actingAsShop($shopA)->getJson('/api/v1/customers');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_a_shop_cannot_fetch_another_shops_customer_by_id(): void
    {
        $shopA = $this->subscribedShop();
        $shopB = $this->subscribedShop();

        $customerInB = Customer::factory()->for($shopB)->create();

        // Route-model binding resolves through TenantScope — a shop-A
        // session referencing shop B's customer ID must 404, not leak
        // shop B's points history.
        $this->actingAsShop($shopA)
            ->getJson("/api/v1/customers/{$customerInB->id}/points")
            ->assertStatus(404);
    }

    public function test_a_shops_session_token_cannot_be_used_to_resolve_a_different_shop(): void
    {
        $shopA = $this->subscribedShop();
        $shopB = $this->subscribedShop();

        // actingAsShop(shopA) mints a JWT whose `dest` claim is shop A's
        // domain — confirm the resolved tenant really is A, never B,
        // regardless of what other shops exist in the database.
        $response = $this->actingAsShop($shopA)->getJson('/api/v1/shop');

        $response->assertOk()
            ->assertJsonPath('data.domain', $shopA->shopify_domain);

        $this->assertNotSame($shopB->shopify_domain, $response->json('data.domain'));
    }

    /**
     * No HTTP order-listing endpoint exists yet in this milestone's
     * scope (customer/order sync is the focus, not a merchant-facing
     * order UI — see docs/SYNCHRONIZATION.md) — this confirms tenant
     * isolation holds at the data layer itself (TenantScope, applied via
     * the same BelongsToShop trait every other tenant-owned model uses),
     * which is what any future order-listing endpoint would inherit
     * automatically.
     */
    public function test_orders_are_isolated_by_tenant_scope(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        Order::factory()->for($shopA)->create();
        Order::factory()->for($shopB)->create();

        $ordersVisibleToA = TenantContext::runAs(
            $shopA,
            fn () => Order::query()->get(),
        );

        $this->assertCount(1, $ordersVisibleToA);
        $this->assertSame($shopA->id, $ordersVisibleToA->first()->shop_id);
    }

    public function test_refunds_and_line_items_are_isolated_by_tenant_scope(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $orderA = Order::factory()->for($shopA)->create();
        Order::factory()->for($shopB)->create();

        Refund::factory()->for($shopA)->for($orderA)->create();
        OrderLineItem::factory()->create(['shop_id' => $shopA->id, 'order_id' => $orderA->id]);

        $refundsVisibleToB = TenantContext::runAs(
            $shopB,
            fn () => Refund::query()->get(),
        );
        $lineItemsVisibleToB = TenantContext::runAs(
            $shopB,
            fn () => OrderLineItem::query()->get(),
        );

        $this->assertCount(0, $refundsVisibleToB);
        $this->assertCount(0, $lineItemsVisibleToB);
    }
}
