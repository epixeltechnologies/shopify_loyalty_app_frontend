<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\DowngradeBlockedException;
use App\Models\Shop;
use App\Models\Subscription;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Services\Shopify\ShopifyGraphQLClient;
use RuntimeException;

/**
 * Wraps Shopify Managed Pricing's AppSubscriptionCreate mutation. Actual
 * activation happens asynchronously via the `app_subscriptions/update`
 * webhook (see SubscriptionService::syncFromWebhook(), invoked by
 * HandleSubscriptionUpdatedJob) — this only creates the pending
 * subscription and returns Shopify's hosted confirmation URL.
 *
 * Handles both a fresh subscribe AND a plan change (upgrade or
 * downgrade) through the same mutation — Shopify Managed Pricing
 * treats a plan change as creating a new AppSubscription; Shopify
 * automatically supersedes the shop's prior subscription once the
 * merchant confirms the new one. Downgrades are checked for safety
 * first via PlanService — see docs/BILLING.md#downgrade-safety.
 */
class BillingService
{
    public function __construct(
        private readonly PlanRepositoryInterface $plans,
        private readonly PlanService $planService,
        private readonly SubscriptionService $subscriptions,
        private readonly ShopifyGraphQLClient $shopify,
    ) {}

    /**
     * @throws DowngradeBlockedException if this is a downgrade and the shop exceeds the target plan's limits
     */
    public function createSubscription(Shop $shop, string $planSlug): string
    {
        $plan = $this->plans->findBySlug($planSlug);

        if (! $plan || ! $plan->shopify_plan_handle) {
            throw new RuntimeException("Plan [{$planSlug}] is not configured for Managed Pricing.");
        }

        $currentPlan = $this->subscriptions->currentPlan($shop);

        if ($currentPlan && $this->planService->isDowngrade($currentPlan, $plan)) {
            $eligibility = $this->planService->checkDowngradeEligibility($shop, $plan);

            if (! $eligibility->eligible) {
                throw new DowngradeBlockedException($eligibility);
            }
        }

        $mutation = <<<'GQL'
            mutation AppSubscriptionCreate($name: String!, $returnUrl: URL!, $test: Boolean!, $lineItems: [AppSubscriptionLineItemInput!]!) {
              appSubscriptionCreate(name: $name, returnUrl: $returnUrl, test: $test, lineItems: $lineItems) {
                confirmationUrl
                userErrors { field message }
              }
            }
        GQL;

        $data = $this->shopify->query($shop, $mutation, [
            'name' => $plan->name,
            'returnUrl' => config('shopify.billing.return_url'),
            'test' => (bool) config('shopify.billing.test_mode'),
            'lineItems' => [[
                'plan' => [
                    'appRecurringPricingDetails' => [
                        'price' => ['amount' => $plan->price_monthly_cents / 100, 'currencyCode' => $plan->currency],
                        'interval' => 'EVERY_30_DAYS',
                    ],
                ],
            ]],
        ]);

        $result = $data['appSubscriptionCreate'] ?? [];

        if (! empty($result['userErrors'])) {
            throw new RuntimeException('Shopify billing error: '.collect($result['userErrors'])->pluck('message')->implode('; '));
        }

        $subscription = Subscription::query()->create([
            'shop_id' => $shop->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        $this->subscriptions->recordMerchantInitiatedChange($shop, $subscription, $currentPlan, $plan);

        return $result['confirmationUrl'];
    }
}
