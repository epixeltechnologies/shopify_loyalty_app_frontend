<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\Webhooks\HandleShopUpdatedJob;
use App\Models\Shop;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SignsShopifyWebhooks;
use Tests\TestCase;

/**
 * Covers the core webhook flow end to end: verify -> identify shop ->
 * store event -> idempotency -> dispatch -> process -> settle.
 */
class WebhookProcessingTest extends TestCase
{
    use RefreshDatabase, SignsShopifyWebhooks;

    public function test_a_valid_webhook_is_stored_and_dispatched(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();

        $this->postWebhook('shop/update', ['name' => 'Updated Store'], webhookId: 'whk_1', shopDomain: $shop->shopify_domain)
            ->assertOk()
            ->assertJson(['received' => true]);

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_1')->first();

        $this->assertNotNull($webhook);
        $this->assertSame($shop->id, $webhook->shop_id);
        $this->assertNotNull($webhook->payload_hash);

        Queue::assertPushed(HandleShopUpdatedJob::class, fn ($job) => $job->webhookId === $webhook->id);
    }

    public function test_a_webhook_for_an_unknown_shop_is_still_accepted_with_a_null_shop(): void
    {
        Queue::fake();

        $this->postWebhook('shop/update', ['id' => 1], webhookId: 'whk_unknown', shopDomain: 'never-installed.myshopify.com')
            ->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_unknown')->first();

        $this->assertNotNull($webhook);
        $this->assertNull($webhook->shop_id);
    }

    public function test_the_end_to_end_flow_actually_processes_the_job_and_marks_the_event_processed(): void
    {
        // No Queue::fake() here — QUEUE_CONNECTION=sync in phpunit.xml
        // means the job really runs, so this test exercises the real
        // WebhookJob template (processing -> processed).
        $shop = Shop::factory()->create(['name' => 'Old Name']);

        $this->postWebhook('shop/update', ['name' => 'New Name'], webhookId: 'whk_e2e', shopDomain: $shop->shopify_domain)
            ->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_e2e')->first();

        $this->assertSame('processed', $webhook->status);
        $this->assertNotNull($webhook->processed_at);
        $this->assertSame('New Name', $shop->fresh()->name);
    }

    public function test_a_topic_with_no_registered_handler_is_settled_without_a_job(): void
    {
        Queue::fake();

        $this->postWebhook('carts/update', ['id' => 1], webhookId: 'whk_no_handler')->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_no_handler')->first();

        $this->assertSame('processed', $webhook->status);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_content_without_a_webhook_id_is_still_hashed_for_detection(): void
    {
        Queue::fake();

        $this->postWebhook('shop/update', ['name' => 'Same Content'])->assertOk();
        $this->postWebhook('shop/update', ['name' => 'Same Content'])->assertOk();

        // Without a Shopify webhook ID, each delivery is stored as its
        // own row (correctness requires the ID for true dedup — see
        // migration comment) but both carry the same payload_hash,
        // which is what makes this detectable/auditable rather than silent.
        $hashes = Webhook::query()->pluck('payload_hash')->unique();
        $this->assertCount(1, $hashes);
        $this->assertSame(2, Webhook::query()->count());
    }
}
