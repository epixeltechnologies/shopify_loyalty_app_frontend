<?php

namespace Tests\Feature\Webhooks;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsShopifyWebhooks;
use Tests\TestCase;

class CustomerComplianceWebhookTest extends TestCase
{
    use RefreshDatabase, SignsShopifyWebhooks;

    public function test_customer_data_request_compiles_and_audits_an_export(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '555']);

        $this->postWebhook('customers/data_request', [
            'shop_id' => $shop->shopify_id,
            'customer' => ['id' => 555, 'email' => $customer->email],
            'orders_requested' => [],
        ], webhookId: 'whk_dr_1', shopDomain: $shop->shopify_domain)->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_dr_1')->first();
        $this->assertSame('processed', $webhook->status);

        $audit = AuditLog::query()->where('action', 'gdpr.customer_data_request')->first();
        $this->assertNotNull($audit);
        $this->assertSame($customer->id, $audit->auditable_id);
        $this->assertTrue($audit->changes['export']['found']);
        $this->assertArrayHasKey('point_transactions', $audit->changes['export']);
    }

    public function test_customer_data_request_for_an_unenrolled_customer_does_not_error(): void
    {
        $shop = Shop::factory()->create();

        $this->postWebhook('customers/data_request', [
            'customer' => ['id' => 999999],
        ], webhookId: 'whk_dr_2', shopDomain: $shop->shopify_domain)->assertOk();

        $webhook = Webhook::query()->where('shopify_webhook_id', 'whk_dr_2')->first();
        $this->assertSame('processed', $webhook->status);
    }

    public function test_customer_redact_anonymizes_pii_but_preserves_the_row(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create([
            'shopify_customer_id' => '777',
            'email' => 'real-person@example.com',
            'first_name' => 'Real',
            'last_name' => 'Person',
        ]);

        $this->postWebhook('customers/redact', [
            'customer' => ['id' => 777],
        ], webhookId: 'whk_redact_1', shopDomain: $shop->shopify_domain)->assertOk();

        $customer->refresh();

        $this->assertNull($customer->email);
        $this->assertNull($customer->first_name);
        $this->assertNull($customer->last_name);
        $this->assertStringStartsWith('redacted-', $customer->shopify_customer_id);
        $this->assertSame('suspended', $customer->status);

        // The row itself — and its ID — survive, so historical
        // point_transactions/reward_redemptions/referrals referencing
        // customer_id keep referential integrity.
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_redact_is_audited(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '888']);

        $this->postWebhook('customers/redact', ['customer' => ['id' => 888]], webhookId: 'whk_redact_2', shopDomain: $shop->shopify_domain)
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'gdpr.customer_redacted',
            'auditable_id' => $customer->id,
        ]);
    }
}
