<?php

namespace Tests\Unit\Services\Shopify;

use App\Models\Order;
use App\Models\Refund;
use App\Models\Shop;
use App\Services\Shopify\ShopifyOrderSyncService;
use App\Services\Shopify\ShopifyRefundSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyRefundSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_refund_for_a_known_order_attaches_correctly(): void
    {
        $shop = Shop::factory()->create();
        app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, ['id' => 1, 'total_price' => '100.00', 'currency' => 'USD']);

        $refund = app(ShopifyRefundSyncService::class)->syncFromWebhook($shop, [
            'id' => 10, 'order_id' => 1, 'transactions' => [['amount' => '100.00']],
        ]);

        $order = Order::query()->where('shopify_order_id', '1')->first();
        $this->assertSame($order->id, $refund->order_id);
        $this->assertSame(10000, $refund->amount_cents);
    }

    public function test_a_refund_before_its_order_creates_a_placeholder(): void
    {
        $shop = Shop::factory()->create();

        $refund = app(ShopifyRefundSyncService::class)->syncFromWebhook($shop, [
            'id' => 10, 'order_id' => 999, 'currency' => 'USD', 'transactions' => [['amount' => '20.00']],
        ]);

        $placeholder = Order::query()->where('shopify_order_id', '999')->first();
        $this->assertNotNull($placeholder);
        $this->assertSame($placeholder->id, $refund->order_id);
        $this->assertSame(0, $placeholder->total_cents); // unknown until the real order syncs
    }

    public function test_the_real_order_arriving_later_fills_the_placeholder_not_a_duplicate(): void
    {
        $shop = Shop::factory()->create();
        app(ShopifyRefundSyncService::class)->syncFromWebhook($shop, [
            'id' => 10, 'order_id' => 999, 'currency' => 'USD', 'transactions' => [['amount' => '20.00']],
        ]);

        app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, ['id' => 999, 'total_price' => '80.00', 'currency' => 'USD']);

        $this->assertSame(1, Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '999')->count());
        $this->assertSame(8000, Order::query()->where('shopify_order_id', '999')->first()->total_cents);
    }

    public function test_partial_refund_detection_compares_against_the_orders_total(): void
    {
        $shop = Shop::factory()->create();
        app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, ['id' => 1, 'total_price' => '100.00', 'currency' => 'USD']);

        $refund = app(ShopifyRefundSyncService::class)->syncFromWebhook($shop, [
            'id' => 10, 'order_id' => 1, 'transactions' => [['amount' => '30.00']],
        ]);

        $this->assertTrue($refund->is_partial);
    }

    public function test_syncing_the_same_refund_twice_does_not_duplicate(): void
    {
        $shop = Shop::factory()->create();
        app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, ['id' => 1, 'total_price' => '100.00', 'currency' => 'USD']);
        $service = app(ShopifyRefundSyncService::class);

        $payload = ['id' => 10, 'order_id' => 1, 'transactions' => [['amount' => '30.00']]];
        $service->syncFromWebhook($shop, $payload);
        $service->syncFromWebhook($shop, $payload);

        $this->assertSame(1, Refund::query()->where('shop_id', $shop->id)->where('shopify_refund_id', '10')->count());
    }
}
