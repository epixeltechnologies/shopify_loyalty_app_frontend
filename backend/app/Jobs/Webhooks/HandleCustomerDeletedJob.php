<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Shopify\ShopifyCustomerSyncService;

/**
 * A Shopify-side customer deletion (merchant deleted the customer from
 * Shopify Admin) is DIFFERENT from the GDPR `customers/redact` webhook
 * — this is a routine merchant action, not a legally-mandated erasure
 * request, so the retention bar is lower but the app still shouldn't
 * keep treating the customer as active. Delegates to
 * ShopifyCustomerSyncService, which suspends rather than deletes:
 * historical points/rewards/referral data is preserved, only future
 * participation stops. Full anonymization is reserved for
 * `customers/redact` (HandleCustomerRedactJob), which has an actual
 * legal retention requirement behind it.
 */
class HandleCustomerDeletedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        app(ShopifyCustomerSyncService::class)->syncFromDelete($shop, $webhook->payload);
    }
}
