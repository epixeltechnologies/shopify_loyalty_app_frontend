<?php

namespace Tests\Unit\Services\Shopify;

use App\Models\Order;
use App\Models\Shop;
use App\Services\Shopify\ShopifyOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyOrderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncing_a_new_order_creates_a_row(): void
    {
        $shop = Shop::factory()->create();

        $order = app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, [
            'id' => 1, 'total_price' => '19.99', 'currency' => 'USD',
        ]);

        $this->assertSame('1', $order->shopify_order_id);
        $this->assertSame(1999, $order->total_cents);
    }

    public function test_syncing_the_same_order_twice_updates_rather_than_duplicates(): void
    {
        $shop = Shop::factory()->create();
        $service = app(ShopifyOrderSyncService::class);

        $service->syncFromWebhook($shop, ['id' => 1, 'total_price' => '10.00', 'currency' => 'USD', 'financial_status' => 'pending']);
        $service->syncFromWebhook($shop, ['id' => 1, 'total_price' => '10.00', 'currency' => 'USD', 'financial_status' => 'paid']);

        $this->assertSame(1, Order::query()->where('shop_id', $shop->id)->where('shopify_order_id', '1')->count());
        $this->assertSame('paid', Order::query()->where('shopify_order_id', '1')->first()->financial_status);
    }

    public function test_sync_cancellation_marks_the_existing_order(): void
    {
        $shop = Shop::factory()->create();
        $service = app(ShopifyOrderSyncService::class);
        $service->syncFromWebhook($shop, ['id' => 1, 'total_price' => '10.00', 'currency' => 'USD']);

        $order = $service->syncCancellation($shop, [
            'id' => 1, 'total_price' => '10.00', 'currency' => 'USD',
            'cancelled_at' => '2026-01-01T00:00:00Z', 'cancel_reason' => 'fraud',
        ]);

        $this->assertTrue($order->isCancelled());
        $this->assertSame('fraud', $order->cancel_reason);
    }

    public function test_line_items_are_stored_with_prices_in_cents(): void
    {
        $shop = Shop::factory()->create();

        $order = app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, [
            'id' => 1, 'total_price' => '25.00', 'currency' => 'USD',
            'line_items' => [
                ['title' => 'Widget', 'quantity' => 1, 'price' => '25.00', 'total_discount' => '5.00'],
            ],
        ]);

        $item = $order->lineItems->first();
        $this->assertSame(2500, $item->price_cents);
        $this->assertSame(500, $item->total_discount_cents);
    }

    public function test_an_order_with_no_customer_payload_is_still_stored(): void
    {
        $shop = Shop::factory()->create();

        $order = app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, ['id' => 1, 'total_price' => '10.00', 'currency' => 'USD']);

        $this->assertNull($order->customer_id);
    }
}
