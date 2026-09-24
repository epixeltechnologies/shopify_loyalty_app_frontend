<?php

namespace App\Jobs\Webhooks;

use App\Jobs\Shopify\PurgeShopDataJob;
use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Audit\AuditLogger;

/**
 * Handles Shopify's mandatory `shop/redact` webhook — sent ~48 hours
 * after uninstall (or sooner, on direct merchant request), instructing
 * the app to delete the shop's data. This job itself only records the
 * compliance event and hands the actual (potentially large, chunked)
 * deletion off to PurgeShopDataJob on the same `webhooks` queue — see
 * that job's docblock for the deletion strategy and what's
 * deliberately preserved.
 */
class HandleShopRedactJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return; // already gone — nothing to redact
        }

        app(AuditLogger::class)->log(
            shop: $shop,
            action: 'gdpr.shop_redact_requested',
            actorType: 'shopify_webhook',
        );

        PurgeShopDataJob::dispatch($shop->id)->onQueue('webhooks');
    }
}
