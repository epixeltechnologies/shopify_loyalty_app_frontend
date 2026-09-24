<?php

namespace Tests\Unit\Services\Shopify;

use App\Models\Shop;
use App\Services\Shopify\ShopifyGraphQLClient;
use App\Services\Shopify\WebhookRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_every_non_compliance_topic(): void
    {
        $shop = Shop::factory()->create();

        Http::fake([
            '*/graphql.json' => Http::response([
                'data' => ['webhookSubscriptionCreate' => ['webhookSubscription' => ['id' => '1'], 'userErrors' => []]],
            ]),
        ]);

        $service = new WebhookRegistrationService(app(ShopifyGraphQLClient::class));
        $service->registerAll($shop);

        $configuredTopics = array_keys(config('shopify.webhooks'));
        $complianceTopics = ['customers/redact', 'shop/redact', 'customers/data_request'];
        $expectedRegistrations = count(array_diff($configuredTopics, $complianceTopics));

        Http::assertSentCount($expectedRegistrations);
    }

    public function test_gdpr_compliance_topics_are_never_registered_via_the_api(): void
    {
        $shop = Shop::factory()->create();

        Http::fake([
            '*/graphql.json' => Http::response([
                'data' => ['webhookSubscriptionCreate' => ['webhookSubscription' => ['id' => '1'], 'userErrors' => []]],
            ]),
        ]);

        $service = new WebhookRegistrationService(app(ShopifyGraphQLClient::class));
        $service->registerAll($shop);

        Http::assertNotSent(fn ($request) => str_contains($request->body(), 'CUSTOMERS_REDACT')
            || str_contains($request->body(), 'SHOP_REDACT')
            || str_contains($request->body(), 'CUSTOMERS_DATA_REQUEST'));
    }

    public function test_an_already_registered_webhook_is_not_treated_as_a_failure(): void
    {
        $shop = Shop::factory()->create();

        Http::fake([
            '*/graphql.json' => Http::response([
                'data' => ['webhookSubscriptionCreate' => [
                    'webhookSubscription' => null,
                    'userErrors' => [['field' => ['callbackUrl'], 'message' => 'Address for this topic has already been taken']],
                ]],
            ]),
        ]);

        $service = new WebhookRegistrationService(app(ShopifyGraphQLClient::class));

        // Must not throw — "already exists" is treated as success, not failure.
        $service->registerAll($shop);
        $this->addToAssertionCount(1);
    }

    public function test_a_shopify_api_failure_on_one_topic_does_not_abort_the_rest(): void
    {
        $shop = Shop::factory()->create();

        Http::fake([
            '*/graphql.json' => Http::response('Internal Server Error', 500),
        ]);

        $service = new WebhookRegistrationService(app(ShopifyGraphQLClient::class));

        // Every topic is attempted despite every call failing — no
        // exception propagates out of registerAll().
        $service->registerAll($shop);

        $configuredTopics = array_keys(config('shopify.webhooks'));
        $complianceTopics = ['customers/redact', 'shop/redact', 'customers/data_request'];
        Http::assertSentCount(count(array_diff($configuredTopics, $complianceTopics)));
    }
}
