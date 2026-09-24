<?php

namespace App\Services\Shopify;

use App\Exceptions\Billing\LimitReachedException;
use App\Models\Customer;
use App\Models\Shop;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Customers\CustomerService;
use Illuminate\Support\Facades\Log;

/**
 * Owns turning a Shopify customer webhook payload into a local
 * `Customer` row (or a no-op, or an update) — the orchestration layer
 * `HandleCustomerCreatedJob`/`HandleCustomerUpdatedJob`/`HandleCustomerDeletedJob`
 * delegate to, so those jobs stay thin (per this task's requirement)
 * and this logic is unit-testable without going through the queue/HTTP
 * pipeline.
 *
 * Distinct from `App\Services\Customers\CustomerService`, which owns
 * loyalty-domain enrollment business rules (plan-limit enforcement,
 * referral code generation, the `CustomerEnrolled` event) — this class
 * is the Shopify-data-sync layer sitting in front of it: it decides
 * *whether* a webhook should trigger an enrollment at all, and owns the
 * identity-field-sync/suspension paths that aren't enrollment. Reuses
 * `CustomerService` rather than duplicating its enrollment logic.
 */
class ShopifyCustomerSyncService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly CustomerService $customerService,
    ) {}

    /**
     * `customers/create` — deliberately does NOT enroll. See
     * HandleCustomerCreatedJob's original docblock (preserved in intent
     * here): enrollment is a merchant/customer-triggered decision with
     * plan-limit consequences attached, not something that should
     * happen silently for every Shopify customer record ever created.
     * Returns the existing Customer if one was somehow already enrolled
     * (e.g. via an order webhook that arrived first), else null.
     */
    public function syncFromCreate(Shop $shop, array $payload): ?Customer
    {
        $shopifyCustomerId = (string) ($payload['id'] ?? '');
        if ($shopifyCustomerId === '') {
            return null;
        }

        $customer = $this->customers->findByShopifyCustomerId($shop, $shopifyCustomerId);
        $customer?->update(['last_synced_at' => now()]);

        return $customer;
    }

    /**
     * `customers/update` — syncs identity fields (email, name) if
     * already enrolled. Data-integrity/PII-accuracy upkeep, not loyalty
     * business logic.
     */
    public function syncFromUpdate(Shop $shop, array $payload): ?Customer
    {
        $shopifyCustomerId = (string) ($payload['id'] ?? '');
        if ($shopifyCustomerId === '') {
            return null;
        }

        $customer = $this->customers->findByShopifyCustomerId($shop, $shopifyCustomerId);

        $customer?->update(array_filter([
            'email' => $payload['email'] ?? null,
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'last_synced_at' => now(),
        ], fn ($v) => $v !== null));

        return $customer;
    }

    /**
     * `customers/delete` — a routine merchant-initiated Shopify Admin
     * deletion, NOT the GDPR `customers/redact` webhook (that has a
     * separate, dedicated handler with actual PII anonymization — see
     * HandleCustomerRedactJob). Suspends rather than deletes: historical
     * points/rewards/referral data is preserved, only future
     * participation stops.
     */
    public function syncFromDelete(Shop $shop, array $payload): ?Customer
    {
        $shopifyCustomerId = (string) ($payload['id'] ?? '');
        if ($shopifyCustomerId === '') {
            return null;
        }

        $customer = $this->customers->findByShopifyCustomerId($shop, $shopifyCustomerId);
        $customer?->update(['status' => 'suspended', 'last_synced_at' => now()]);

        return $customer;
    }

    /**
     * Resolves an existing enrolled Customer for a Shopify customer
     * payload, or enrolls one — used by order sync (a purchase is
     * exactly the moment auto-enrollment IS appropriate, per this app's
     * existing product decision). Never lets a plan-limit rejection
     * escape as an uncaught exception; a purchase from a shop already
     * at its customer limit is an expected scenario, not a failure.
     */
    public function findOrEnroll(Shop $shop, array $shopifyCustomer): ?Customer
    {
        if (empty($shopifyCustomer['id'])) {
            return null;
        }

        $existing = $this->customers->findByShopifyCustomerId($shop, (string) $shopifyCustomer['id']);
        if ($existing) {
            $existing->update(['last_synced_at' => now()]);

            return $existing;
        }

        if (! $shop->entitlements()->canAddCustomer()) {
            Log::info('Customer sync skipped enrollment — plan customer limit reached', [
                'shop' => $shop->shopify_domain, 'shopify_customer_id' => $shopifyCustomer['id'],
            ]);

            return null;
        }

        try {
            $customer = $this->customerService->findOrEnrollFromShopify($shop, $shopifyCustomer);
            $customer->update(['last_synced_at' => now()]);

            return $customer;
        } catch (LimitReachedException) {
            Log::info('Customer sync lost a race for the last plan slot', [
                'shop' => $shop->shopify_domain, 'shopify_customer_id' => $shopifyCustomer['id'],
            ]);

            return null;
        }
    }
}
