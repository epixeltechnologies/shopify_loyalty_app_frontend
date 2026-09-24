<?php

namespace Tests\Feature\Webhooks;

use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SignsShopifyWebhooks;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase, SignsShopifyWebhooks;

    public function test_a_redelivered_webhook_is_not_processed_twice(): void
    {
        Queue::fake();

        $this->postWebhook('shop/update', ['id' => 12345], webhookId: 'whk_duplicate_test', shopDomain: 'test-shop.myshopify.com')
            ->assertOk();
        $this->postWebhook('shop/update', ['id' => 12345], webhookId: 'whk_duplicate_test', shopDomain: 'test-shop.myshopify.com')
            ->assertOk();

        $this->assertSame(1, Webhook::query()->where('shopify_webhook_id', 'whk_duplicate_test')->count());
    }

    public function test_a_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $this->call('POST', '/webhooks/shop/update', [], [], [], $this->transformHeadersToServerVars([
            'X-Shopify-Hmac-Sha256' => 'not-a-valid-signature',
            'Content-Type' => 'application/json',
        ]), json_encode(['id' => 1]))->assertStatus(401);
    }
}
