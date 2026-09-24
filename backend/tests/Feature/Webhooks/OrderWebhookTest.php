<?php

namespace Tests\Feature\Webhooks;

use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsShopifyWebhooks;
use Tests\TestCase;

/**
 * Confirms reliable order-event capture (analytics recording +
 * customer resolution/enrollment + the normalized `orders`/
 * `order_line_items`/`refunds` tables ShopifyOrderSyncService/
 * ShopifyRefundSyncService populate) WITHOUT any points being
 * calculated — see each job's docblock and docs/SYNCHRONIZATION.md.
 * Idempotency at the webhook-delivery level (never double-processing
 * the same webhook) is guaranteed at the WebhookController layer via
 * `shopify_webhook_id`'s unique constraint, already covered by
 * WebhookIdempotencyTest; idempotency at the ORDER-ROW level (an
 * `orders/create` followed by `orders/updated` for the same order
 * converging on one row) is covered here directly.
 */
class OrderWebhookTest extends TestCase
{
    use RefreshDatabase, SignsShopifyWebhooks;

    private function subscribedShop(): Shop
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        return $shop;
    }

    public function test_orders_create_enrolls_the_customer_and_records_an_event(): void
    {
        $shop = $this->subscribedShop();

        $this->postWebhook('orders/create', [
            'id' => 1001,
            'total_price' => '49.99',
            'currency' => 'USD',
            'customer' => ['id' => 4242, 'email' => 'buyer@example.com', 'first_name' => 'Buyer'],
        ], webhookId: 'whk_order_1', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertDatabaseHas('customers', ['shop_id' => $shop->id, 'shopify_customer_id' => '4242']);

        $event = AnalyticsEvent::query()->where('event_type', 'order.created')->first();
        $this->assertNotNull($event);
        $this->assertSame('1001', $event->properties['order_id']);

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_order_1')->first();
        $this->assertSame('processed', $webhook->status);
    }

    public function test_orders_create_stores_a_normalized_order_row_in_cents(): void
    {
        $shop = $this->subscribedShop();

        $this->postWebhook('orders/create', [
            'id' => 1010,
            'order_number' => '1010',
            'total_price' => '49.99',
            'subtotal_price' => '44.99',
            'total_tax' => '5.00',
            'currency' => 'USD',
            'financial_status' => 'paid',
        ], webhookId: 'whk_order_cents', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '1010')->first();

        $this->assertNotNull($order);
        $this->assertSame(4999, $order->total_cents);
        $this->assertSame(4499, $order->subtotal_cents);
        $this->assertSame(500, $order->total_tax_cents);
        $this->assertSame('paid', $order->financial_status);
    }

    public function test_orders_create_stores_line_items_with_product_metadata(): void
    {
        $shop = $this->subscribedShop();

        $this->postWebhook('orders/create', [
            'id' => 1011,
            'total_price' => '30.00',
            'currency' => 'USD',
            'line_items' => [
                ['id' => 1, 'product_id' => 555, 'variant_id' => 777, 'title' => 'Cool Shirt', 'vendor' => 'Acme', 'product_type' => 'Apparel', 'quantity' => 2, 'price' => '15.00'],
            ],
        ], webhookId: 'whk_order_line_items', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '1011')->first();

        $this->assertCount(1, $order->lineItems);
        $lineItem = $order->lineItems->first();
        $this->assertSame('555', $lineItem->shopify_product_id);
        $this->assertSame('Acme', $lineItem->vendor);
        $this->assertSame('Apparel', $lineItem->product_type);
        $this->assertSame(2, $lineItem->quantity);
        $this->assertSame(1500, $lineItem->price_cents);
    }

    public function test_orders_create_when_the_plan_customer_limit_is_reached_still_records_the_event(): void
    {
        $shop = $this->subscribedShop(); // Starter plan: max_active_customers = 500
        Customer::factory()->for($shop)->count(500)->create(); // at the limit

        $this->postWebhook('orders/create', [
            'id' => 1003,
            'customer' => ['id' => 7777, 'email' => 'overflow@example.com'],
        ], webhookId: 'whk_order_limit', shopDomain: $shop->shopify_domain)->assertOk();

        // The webhook must settle successfully — a plan limit is an
        // expected business condition, never a reason for the webhook
        // pipeline itself to fail.
        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_order_limit')->first();
        $this->assertSame('processed', $webhook->status);
        $this->assertDatabaseMissing('customers', ['shop_id' => $shop->id, 'shopify_customer_id' => '7777']);

        $event = AnalyticsEvent::query()->where('event_type', 'order.created')->first();
        $this->assertNull($event->customer_id);

        // The order itself is still recorded even without a customer association.
        $this->assertDatabaseHas('orders', ['shop_id' => $shop->id, 'shopify_order_id' => '1003', 'customer_id' => null]);
    }

    public function test_orders_create_without_a_customer_payload_does_not_error(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('orders/create', ['id' => 1002, 'total_price' => '10.00'], webhookId: 'whk_order_2', shopDomain: $shop->shopify_domain)
            ->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_order_2')->first();
        $this->assertSame('processed', $webhook->status);
        $this->assertSame(0, Customer::query()->where('shop_id', $shop->id)->count());
    }

    public function test_orders_updated_records_the_new_status(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('orders/updated', [
            'id' => 2001, 'financial_status' => 'paid', 'fulfillment_status' => 'fulfilled',
        ], webhookId: 'whk_order_upd', shopDomain: $shop->shopify_domain)->assertOk();

        $event = AnalyticsEvent::query()->where('event_type', 'order.updated')->first();
        $this->assertSame('paid', $event->properties['financial_status']);
    }

    public function test_an_order_create_followed_by_an_update_converges_on_one_row(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('orders/create', [
            'id' => 2010, 'total_price' => '20.00', 'currency' => 'USD', 'financial_status' => 'pending',
        ], webhookId: 'whk_order_create_then_update_1', shopDomain: $shop->shopify_domain)->assertOk();

        $this->postWebhook('orders/updated', [
            'id' => 2010, 'total_price' => '20.00', 'currency' => 'USD', 'financial_status' => 'paid',
        ], webhookId: 'whk_order_create_then_update_2', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(1, Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '2010')->count());
        $this->assertSame('paid', Order::query()->where('shopify_order_id', '2010')->first()->financial_status);
    }

    public function test_re_syncing_an_order_replaces_line_items_rather_than_duplicating_them(): void
    {
        $shop = Shop::factory()->create();
        $payload = fn (array $items) => [
            'id' => 2020, 'total_price' => '20.00', 'currency' => 'USD', 'line_items' => $items,
        ];

        $this->postWebhook('orders/create', $payload([
            ['id' => 1, 'title' => 'Item A', 'quantity' => 1, 'price' => '10.00'],
            ['id' => 2, 'title' => 'Item B', 'quantity' => 1, 'price' => '10.00'],
        ]), webhookId: 'whk_line_items_1', shopDomain: $shop->shopify_domain)->assertOk();

        // A subsequent sync (e.g. an item removed before fulfillment) sends a different item set.
        $this->postWebhook('orders/updated', $payload([
            ['id' => 1, 'title' => 'Item A', 'quantity' => 1, 'price' => '10.00'],
        ]), webhookId: 'whk_line_items_2', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '2020')->first();
        $this->assertCount(1, $order->lineItems);
    }

    public function test_orders_cancelled_records_the_event_and_marks_the_order(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('orders/cancelled', [
            'id' => 3001, 'cancel_reason' => 'customer', 'cancelled_at' => now()->toIso8601String(), 'currency' => 'USD',
        ], webhookId: 'whk_order_cancel', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertDatabaseHas('analytics_events', ['event_type' => 'order.cancelled']);

        $order = Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '3001')->first();
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('customer', $order->cancel_reason);
    }

    public function test_refunds_create_records_the_event_and_stores_a_refund_row(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('refunds/create', [
            'id' => 5001, 'order_id' => 3001, 'transactions' => [['amount' => '10.00']],
        ], webhookId: 'whk_refund_1', shopDomain: $shop->shopify_domain)->assertOk();

        $event = AnalyticsEvent::query()->where('event_type', 'refund.created')->first();
        $this->assertNotNull($event);
        $this->assertSame('3001', $event->properties['order_id']);

        $this->assertDatabaseHas('refunds', ['shop_id' => $shop->id, 'shopify_refund_id' => '5001', 'amount_cents' => 1000]);
    }

    public function test_a_refund_arriving_before_its_order_creates_a_placeholder_order(): void
    {
        $shop = Shop::factory()->create();

        // No orders/create has been received for order 9999 yet.
        $this->postWebhook('refunds/create', [
            'id' => 6001, 'order_id' => 9999, 'currency' => 'USD', 'transactions' => [['amount' => '5.00']],
        ], webhookId: 'whk_refund_before_order', shopDomain: $shop->shopify_domain)->assertOk();

        $order = Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '9999')->first();
        $this->assertNotNull($order);

        $refund = Refund::query()->where('shopify_refund_id', '6001')->first();
        $this->assertSame($order->id, $refund->order_id);

        // The real order webhook, whenever it arrives, fills in the placeholder rather than duplicating it.
        $this->postWebhook('orders/create', [
            'id' => 9999, 'total_price' => '50.00', 'currency' => 'USD',
        ], webhookId: 'whk_order_fills_placeholder', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(1, Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '9999')->count());
        $this->assertSame(5000, Order::query()->where('shopify_order_id', '9999')->first()->total_cents);
    }

    public function test_a_partial_refund_is_flagged_as_partial(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('orders/create', [
            'id' => 7001, 'total_price' => '100.00', 'currency' => 'USD',
        ], webhookId: 'whk_order_for_partial_refund', shopDomain: $shop->shopify_domain)->assertOk();

        $this->postWebhook('refunds/create', [
            'id' => 7002, 'order_id' => 7001, 'transactions' => [['amount' => '25.00']],
        ], webhookId: 'whk_partial_refund', shopDomain: $shop->shopify_domain)->assertOk();

        $refund = Refund::query()->where('shopify_refund_id', '7002')->first();
        $this->assertTrue($refund->is_partial);
        $this->assertSame(2500, $refund->amount_cents);
    }

    public function test_a_full_refund_is_not_flagged_as_partial(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('orders/create', [
            'id' => 7101, 'total_price' => '100.00', 'currency' => 'USD',
        ], webhookId: 'whk_order_for_full_refund', shopDomain: $shop->shopify_domain)->assertOk();

        $this->postWebhook('refunds/create', [
            'id' => 7102, 'order_id' => 7101, 'transactions' => [['amount' => '100.00']],
        ], webhookId: 'whk_full_refund', shopDomain: $shop->shopify_domain)->assertOk();

        $refund = Refund::query()->where('shopify_refund_id', '7102')->first();
        $this->assertFalse($refund->is_partial);
    }

    public function test_a_duplicate_refund_webhook_does_not_create_a_second_row(): void
    {
        $shop = Shop::factory()->create();

        $payload = ['id' => 8001, 'order_id' => 8000, 'currency' => 'USD', 'transactions' => [['amount' => '10.00']]];

        $this->postWebhook('refunds/create', $payload, webhookId: 'whk_dup_refund', shopDomain: $shop->shopify_domain)->assertOk();
        $this->postWebhook('refunds/create', $payload, webhookId: 'whk_dup_refund', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(1, Refund::query()->where('shopify_refund_id', '8001')->count());
    }

    public function test_a_duplicate_order_across_two_different_webhook_ids_still_converges_via_upsert(): void
    {
        // Simulates a redelivery scenario where Shopify's webhook ID
        // differs (rare, but possible for e.g. a manually re-triggered
        // event) — the order-row-level idempotency (shop_id,
        // shopify_order_id) is the second, independent safety net
        // beyond the webhook-delivery-level one.
        $shop = Shop::factory()->create();

        $this->postWebhook('orders/create', ['id' => 4001, 'total_price' => '15.00', 'currency' => 'USD'], webhookId: 'whk_a', shopDomain: $shop->shopify_domain)->assertOk();
        $this->postWebhook('orders/create', ['id' => 4001, 'total_price' => '15.00', 'currency' => 'USD'], webhookId: 'whk_b', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(1, Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '4001')->count());
    }

    public function test_order_events_for_an_unresolvable_shop_do_not_error(): void
    {
        $this->postWebhook('orders/create', ['id' => 9998], webhookId: 'whk_order_no_shop')->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_order_no_shop')->first();
        $this->assertSame('processed', $webhook->status);
        $this->assertSame(0, AnalyticsEvent::query()->count());
    }
}
