<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Analytics\EventRecorder;
use App\Services\Shopify\ShopifyCustomerSyncService;

/**
 * Deliberately does NOT auto-enroll the new Shopify customer into the
 * loyalty program — enrollment is a merchant/customer-triggered
 * decision (see CustomerController::store() / CustomerService::enroll())
 * with plan-limit enforcement attached to it, not something that should
 * happen silently for every Shopify customer record ever created,
 * regardless of whether they've ever engaged with the loyalty widget.
 * Delegates to ShopifyCustomerSyncService (which stamps
 * `last_synced_at` if the customer happens to already be enrolled —
 * e.g. via an order that arrived first) and records the event for
 * analytics/future reference.
 */
class HandleCustomerCreatedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        $customer = app(ShopifyCustomerSyncService::class)->syncFromCreate($shop, $webhook->payload);

        app(EventRecorder::class)->record($shop, 'shopify_customer.created', $customer, [
            'shopify_customer_id' => $webhook->payload['id'] ?? null,
        ]);
    }
}
