<?php

namespace App\Jobs\Webhooks;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\Webhook;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Audit\AuditLogger;

/**
 * Handles Shopify's mandatory `customers/data_request` webhook — the
 * merchant (on the customer's behalf) is requesting a copy of
 * everything the app has stored about that customer. Shopify requires
 * a response within 30 days; it does not prescribe a transport
 * mechanism (email, a support ticket, an API callback are all
 * acceptable), so this job compiles the full export and records it via
 * the audit trail (`AuditLogger` — the `changes` payload holds the
 * compiled data) for an operator to retrieve and deliver through
 * whatever channel the merchant relationship uses. Automated delivery
 * (e.g. emailing the store owner directly) is a reasonable future
 * enhancement, not implemented here — this job's responsibility is
 * making sure the DATA is compiled and durably recorded, satisfying the
 * "prepare the data" requirement even without a delivery integration.
 */
class HandleCustomerDataRequestJob extends WebhookJob
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

        $export = $customer ? $this->compileExport($customer) : ['found' => false];

        app(AuditLogger::class)->log(
            shop: $shop,
            action: 'gdpr.customer_data_request',
            actorType: 'shopify_webhook',
            auditableType: $customer ? get_class($customer) : null,
            auditableId: $customer?->id,
            changes: ['export' => $export],
        );
    }

    private function compileExport(Customer $customer): array
    {
        return [
            'found' => true,
            'profile' => [
                'email' => $customer->email,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'status' => $customer->status,
                'enrolled_at' => $customer->enrolled_at?->toIso8601String(),
            ],
            'points_balance' => $customer->points_balance,
            'point_transactions' => $customer->pointTransactions()->get(['direction', 'points', 'source', 'created_at'])->toArray(),
            'reward_redemptions' => $customer->rewardRedemptions()->get(['reward_id', 'points_spent', 'status', 'created_at'])->toArray(),
            'referrals_made' => $customer->referralsMade()->get(['referral_code', 'status', 'completed_at'])->toArray(),
            'vip_tier_history' => $customer->vipHistory()->get(['direction', 'lifetime_points_at_change', 'created_at'])->toArray(),
        ];
    }
}
