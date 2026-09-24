<?php

namespace Tests\Feature\Webhooks;

use App\Models\Order;
use App\Models\Plan;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsShopifyWebhooks;
use Tests\TestCase;

/**
 * End-to-end: a real webhook payload through WebhookController ->
 * ShopifyOrderSyncService -> PointsAccrualService, exercising the full
 * pipeline this milestone wires together, not just the services in
 * isolation (see PointsAccrualServiceTest for that).
 */
class OrderPointsAccrualTest extends TestCase
{
    use RefreshDatabase, SignsShopifyWebhooks;

    private function subscribedShop(): Shop
    {
        $shop = Shop::factory()->create();
        Subscription::factory()->for($shop)->for(Plan::factory()->starter()->create())->create(['status' => 'active']);

        return $shop;
    }

    public function test_a_paid_order_webhook_awards_points_end_to_end(): void
    {
        $shop = $this->subscribedShop();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $this->postWebhook('orders/create', [
            'id' => 9001,
            'financial_status' => 'paid',
            'subtotal_price' => '40.00',
            'total_price' => '40.00',
            'currency' => 'USD',
            'customer' => ['id' => 5555, 'email' => 'buyer@example.com'],
        ], webhookId: 'whk_paid_order', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shopify_order_id', '9001')->first();
        $this->assertNotNull($order->customer);
        $this->assertSame(40, $order->customer->point->fresh()->balance);
    }

    public function test_an_unpaid_order_earns_nothing_until_updated_to_paid(): void
    {
        $shop = $this->subscribedShop();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $this->postWebhook('orders/create', [
            'id' => 9002, 'financial_status' => 'pending', 'subtotal_price' => '40.00', 'total_price' => '40.00',
            'customer' => ['id' => 5556],
        ], webhookId: 'whk_unpaid', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shopify_order_id', '9002')->first();
        $this->assertSame(0, $order->customer->point->fresh()->balance);

        $this->postWebhook('orders/updated', [
            'id' => 9002, 'financial_status' => 'paid', 'subtotal_price' => '40.00', 'total_price' => '40.00',
        ], webhookId: 'whk_now_paid', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(40, $order->customer->fresh()->point->fresh()->balance);
    }

    public function test_a_cancelled_order_reverses_previously_awarded_points(): void
    {
        $shop = $this->subscribedShop();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $this->postWebhook('orders/create', [
            'id' => 9003, 'financial_status' => 'paid', 'subtotal_price' => '30.00', 'total_price' => '30.00',
            'customer' => ['id' => 5557],
        ], webhookId: 'whk_to_cancel', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shopify_order_id', '9003')->first();
        $this->assertSame(30, $order->customer->point->fresh()->balance);

        $this->postWebhook('orders/cancelled', [
            'id' => 9003, 'financial_status' => 'paid', 'cancelled_at' => now()->toIso8601String(), 'cancel_reason' => 'customer',
        ], webhookId: 'whk_cancel', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(0, $order->customer->fresh()->point->fresh()->balance);
    }

    public function test_a_refund_proportionally_reverses_points(): void
    {
        $shop = $this->subscribedShop();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $this->postWebhook('orders/create', [
            'id' => 9004, 'financial_status' => 'paid', 'subtotal_price' => '100.00', 'total_price' => '100.00',
            'customer' => ['id' => 5558],
        ], webhookId: 'whk_to_refund', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shopify_order_id', '9004')->first();
        $this->assertSame(100, $order->customer->point->fresh()->balance);

        $this->postWebhook('refunds/create', [
            'id' => 7001, 'order_id' => 9004, 'transactions' => [['amount' => '50.00']],
        ], webhookId: 'whk_refund', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(50, $order->customer->fresh()->point->fresh()->balance);
        $this->assertDatabaseHas('point_transactions', [
            'customer_id' => $order->customer->id,
            'source' => PointTransaction::SOURCE_REFUND_REVERSAL,
        ]);
    }

    public function test_no_active_subscription_means_no_points_are_processed(): void
    {
        // Shop exists but has never subscribed — order sync still
        // happens (order history matters regardless of billing state),
        // but points must never accrue for an unsubscribed shop.
        $shop = Shop::factory()->create();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $this->postWebhook('orders/create', [
            'id' => 9005, 'financial_status' => 'paid', 'subtotal_price' => '40.00', 'total_price' => '40.00',
            'customer' => ['id' => 5559],
        ], webhookId: 'whk_no_sub', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shopify_order_id', '9005')->first();
        $this->assertNotNull($order); // order is still synced
        $this->assertSame(0, $order->customer->point->fresh()->balance); // but no points
    }

    public function test_redelivery_of_the_same_order_webhook_never_double_awards(): void
    {
        $shop = $this->subscribedShop();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $payload = ['id' => 9006, 'financial_status' => 'paid', 'subtotal_price' => '20.00', 'total_price' => '20.00', 'customer' => ['id' => 5560]];

        $this->postWebhook('orders/create', $payload, webhookId: 'whk_redelivery', shopDomain: $shop->shopify_domain)->assertOk();
        $this->postWebhook('orders/create', $payload, webhookId: 'whk_redelivery', shopDomain: $shop->shopify_domain)->assertOk(); // same webhook ID = redelivery

        $order = Order::query()->where('shopify_order_id', '9006')->first();
        $this->assertSame(20, $order->customer->point->fresh()->balance);
    }
}
