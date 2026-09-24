<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;

/**
 * Keeps cached Shopify store metadata (name, email, currency, timezone,
 * Shopify's own plan display name) in sync — non-loyalty housekeeping,
 * safe to implement now.
 */
class HandleShopUpdatedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        $payload = $webhook->payload;

        $shop->update(array_filter([
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'timezone' => $payload['iana_timezone'] ?? $payload['timezone'] ?? null,
            'plan_display_name' => $payload['plan_name'] ?? null,
        ], fn ($v) => $v !== null));
    }
}
