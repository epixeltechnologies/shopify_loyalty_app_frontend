<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Shopify\ShopifyCustomerSyncService;

/**
 * Keeps a loyalty member's identity fields (email, name) in sync with
 * their Shopify customer record — data-integrity/PII-accuracy upkeep,
 * not loyalty business logic. Delegates to ShopifyCustomerSyncService;
 * only acts if the shopify customer is ALREADY an enrolled loyalty
 * `Customer` (see HandleCustomerCreatedJob's docblock for why
 * enrollment itself doesn't happen automatically here).
 */
class HandleCustomerUpdatedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        app(ShopifyCustomerSyncService::class)->syncFromUpdate($shop, $webhook->payload);
    }
}
