<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Str;

/**
 * Handles Shopify's mandatory `customers/redact` webhook — a legally
 * mandated erasure request for one specific customer (distinct from
 * `shop/redact`, which is the whole store). Anonymizes personal
 * information (email, first/last name) while PRESERVING the row itself
 * and every historical ledger/redemption/referral record that
 * references it — points_transactions, reward_redemptions, referrals,
 * and customer_vip_history all keep their `customer_id` foreign key
 * intact, satisfying "maintain referential integrity" without
 * retaining any personally identifying information on the anonymized
 * row itself. `shopify_customer_id` is replaced with a redaction
 * marker (not left as the real Shopify ID) since that value is itself
 * PII-adjacent and is Shopify's own means of re-identifying the person.
 */
class HandleCustomerRedactJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        $shopifyCustomerId = (string) ($webhook->payload['customer']['id'] ?? '');

        if ($shopifyCustomerId === '') {
            return;
        }

        $customer = app(CustomerRepositoryInterface::class)->findByShopifyCustomerId($shop, $shopifyCustomerId);

        if (! $customer) {
            return; // never enrolled — nothing to redact
        }

        $customer->update([
            'email' => null,
            'first_name' => null,
            'last_name' => null,
            'shopify_customer_id' => 'redacted-'.Str::uuid(),
            'status' => 'suspended',
        ]);

        app(AuditLogger::class)->log(
            shop: $shop,
            action: 'gdpr.customer_redacted',
            actorType: 'shopify_webhook',
            auditableType: get_class($customer),
            auditableId: $customer->id,
        );
    }
}
