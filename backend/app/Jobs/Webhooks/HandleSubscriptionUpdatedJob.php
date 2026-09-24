<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Billing\SubscriptionService;
use App\Services\Cache\CacheService;

/**
 * Handles Shopify's `app_subscriptions/update` webhook — the
 * authoritative signal that a subscription's status changed (approved,
 * declined, cancelled, frozen for a failed charge, expired, ...).
 * Delegates the actual status mapping / SubscriptionEvent recording to
 * SubscriptionService::syncFromWebhook(), then invalidates the shop's
 * cached entitlements so the change is reflected immediately rather
 * than waiting out the cache TTL.
 */
class HandleSubscriptionUpdatedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        app(SubscriptionService::class)->syncFromWebhook($shop, $webhook->payload);

        app(CacheService::class)->forgetShop($shop->id);
    }
}
