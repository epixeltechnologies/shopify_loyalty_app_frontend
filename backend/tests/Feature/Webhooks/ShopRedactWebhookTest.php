<?php

namespace Tests\Feature\Webhooks;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\VipTier;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsShopifyWebhooks;
use Tests\TestCase;

class ShopRedactWebhookTest extends TestCase
{
    use RefreshDatabase, SignsShopifyWebhooks;

    public function test_shop_redact_purges_the_shop_and_its_data(): void
    {
        $shop = Shop::factory()->create();
        ShopSetting::factory()->for($shop)->create();
        $tier = VipTier::factory()->for($shop)->create();
        Customer::factory()->for($shop)->count(3)->create();

        $this->postWebhook('shop/redact', [
            'shop_id' => $shop->shopify_id,
            'shop_domain' => $shop->shopify_domain,
        ], webhookId: 'whk_shop_redact', shopDomain: $shop->shopify_domain)->assertOk();

        // Both HandleShopRedactJob (queues the purge) and PurgeShopDataJob
        // itself run synchronously in tests (QUEUE_CONNECTION=sync), so
        // by the time the request returns, the purge has completed.
        $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
        $this->assertDatabaseMissing('customers', ['shop_id' => $shop->id]);
        $this->assertDatabaseMissing('vip_tiers', ['id' => $tier->id]);
        $this->assertDatabaseMissing('shop_settings', ['shop_id' => $shop->id]);
    }

    public function test_shop_redact_preserves_the_audit_trail(): void
    {
        $shop = Shop::factory()->create();
        $shopId = $shop->id;

        $this->postWebhook('shop/redact', ['shop_id' => $shop->shopify_id], webhookId: 'whk_shop_redact_2', shopDomain: $shop->shopify_domain)
            ->assertOk();

        // The webhook + audit log rows survive the shop's own deletion
        // (nullOnDelete on shop_id) — the compliance record of "this shop
        // was redacted, and when" is retained even though the shop is gone.
        $this->assertDatabaseHas('audit_logs', ['action' => 'gdpr.shop_redact_requested']);
        $this->assertDatabaseHas('webhooks', ['shopify_webhook_id' => 'whk_shop_redact_2']);
        $this->assertDatabaseMissing('shops', ['id' => $shopId]);
    }

    public function test_shop_redact_for_an_already_removed_shop_does_not_error(): void
    {
        // No matching shop at all — the domain doesn't resolve to a Shop row.
        $this->postWebhook('shop/redact', ['shop_id' => 999999], webhookId: 'whk_shop_redact_unknown')
            ->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_shop_redact_unknown')->first();
        $this->assertSame('processed', $webhook->status);
    }
}
