<?php

namespace App\Services\Customers;

use App\Events\Customers\CustomerEnrolled;
use App\Exceptions\Billing\LimitReachedException;
use App\Models\Point;
use App\Models\Shop;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Billing\UsageLimitService;
use App\Support\Str\ReferralCodeGenerator;
use Illuminate\Support\Facades\DB;

/**
 * CUSTOMERS: owns enrollment (the write path that must respect the
 * shop's plan customer limit) and lookup of loyalty members. Also
 * provisions the customer's 1:1 `Point` row at enrollment time, so
 * every other service can assume `$customer->point` exists rather than
 * null-checking it everywhere.
 */
class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly UsageLimitService $usageLimits,
        private readonly ReferralCodeGenerator $codes,
    ) {}

    /**
     * @throws LimitReachedException if the shop's plan customer limit is already reached
     */
    public function enroll(Shop $shop, array $attributes)
    {
        return DB::transaction(function () use ($shop, $attributes) {
            $plan = $shop->entitlements()->plan();

            // Reserve the usage slot FIRST, inside this transaction, via
            // an atomically-locked check-and-increment — this closes the
            // race a separate "if (canAddCustomer()) { create(); }"
            // sequence has under concurrent enrollment (e.g. two
            // near-simultaneous order webhooks for different new
            // customers on the same shop). See
            // UsageTrackerService::tryIncrementIfUnderLimit()'s docblock.
            if (! $this->usageLimits->tryReserveCustomerSlot($shop, $plan)) {
                $limit = $this->usageLimits->limitFor($plan, 'max_active_customers');

                throw new LimitReachedException(
                    feature: 'active_customers',
                    currentUsage: $limit ?? 0,
                    limit: $limit ?? 0,
                    requiredPlan: $plan?->slug === 'starter' ? 'professional' : null,
                );
            }

            $customer = $this->customers->create([
                ...$attributes,
                'shop_id' => $shop->id,
                'referral_code' => $this->codes->generateUnique($shop),
                'status' => 'active',
                'enrolled_at' => now(),
            ]);

            Point::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);

            event(new CustomerEnrolled($customer));

            return $customer;
        });
    }

    public function findOrEnrollFromShopify(Shop $shop, array $shopifyCustomer)
    {
        return $this->customers->findByShopifyCustomerId($shop, (string) $shopifyCustomer['id'])
            ?? $this->enroll($shop, [
                'shopify_customer_id' => (string) $shopifyCustomer['id'],
                'email' => $shopifyCustomer['email'] ?? null,
                'first_name' => $shopifyCustomer['first_name'] ?? null,
                'last_name' => $shopifyCustomer['last_name'] ?? null,
            ]);
    }
}
