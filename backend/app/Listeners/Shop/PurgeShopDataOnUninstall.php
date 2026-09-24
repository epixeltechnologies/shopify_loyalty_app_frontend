<?php

namespace App\Listeners\Shop;

use App\Events\Shop\ShopUninstalled;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Deliberately does NOT delete or purge any loyalty data. Per Shopify's
 * data-retention rules, a merchant's customers/points/rewards data must
 * be retained after a plain uninstall — hard deletion is only triggered
 * by the separate, mandatory `shop/redact` GDPR webhook (sent by
 * Shopify ~48 hours after uninstall, or on direct merchant request),
 * which will get its own dedicated purge job when the compliance
 * webhooks are implemented (see docs/NEXT_STEPS.md).
 *
 * What this listener DOES do: records the uninstall as an audit event,
 * since "shop uninstalled the app" is exactly the kind of
 * security/compliance-relevant fact docs/SECURITY.md's audit trail
 * exists to capture.
 */
class PurgeShopDataOnUninstall implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(ShopUninstalled $event): void
    {
        $this->audit->log(
            shop: $event->shop,
            action: 'shop.uninstalled',
            actorType: 'shopify_webhook',
        );
    }
}
