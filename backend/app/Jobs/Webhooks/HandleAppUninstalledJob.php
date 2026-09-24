<?php

namespace App\Jobs\Webhooks;

use App\Events\Shop\ShopUninstalled;
use App\Models\Shop;
use App\Models\Webhook;
use App\Repositories\Contracts\ShopRepositoryInterface;

/**
 * Handles Shopify's `app/uninstalled` webhook — the authoritative
 * signal that a merchant has removed the app. Per docs/AUTHENTICATION.md's
 * uninstall section:
 *   - marks the shop uninstalled (ShopRepository::markUninstalled sets
 *     `is_installed = false`, `uninstalled_at = now()`, and clears the
 *     access token)
 *   - does NOT delete loyalty data — that only happens on the separate
 *     `shop/redact` GDPR webhook (HandleShopRedactJob), and even then
 *     via a dedicated, deliberate purge, not as a side effect of this one
 *   - `EnsureShopIsActive` middleware is what actually prevents further
 *     merchant access once `is_installed` is false — this job's only
 *     responsibility is flipping that flag
 *
 * Shop identification, idempotency (won't reprocess an already-settled
 * event), and retry/failure bookkeeping are all handled by the base
 * WebhookJob — this class only implements the topic-specific action.
 */
class HandleAppUninstalledJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop || ! $shop->is_installed) {
            return; // unresolvable shop, or already marked uninstalled by a prior delivery
        }

        app(ShopRepositoryInterface::class)->markUninstalled($shop);

        event(new ShopUninstalled($shop));
    }
}
