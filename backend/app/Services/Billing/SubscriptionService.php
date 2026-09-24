<?php

namespace App\Services\Billing;

use App\Events\Subscription\SubscriptionActivated;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BILLING: owns subscription retrieval and status synchronization —
 * "is this shop's subscription actually active right now, and how did
 * it get that way." Complements BillingService (which only initiates a
 * NEW subscription via Shopify Managed Pricing),
 * SubscriptionAccessService (which owns the *combined* shop-active +
 * subscription-active access gate this class's `isActive()` delegates
 * to), and EntitlementService (which answers "can this shop do X" from
 * whatever the current subscription/plan already is). See
 * docs/BILLING.md and docs/ENTITLEMENTS.md.
 */
class SubscriptionService
{
    public function __construct(
        private readonly PlanRepositoryInterface $plans,
        private readonly SubscriptionAccessService $access,
    ) {}

    public function current(Shop $shop): ?Subscription
    {
        return $shop->activeSubscription;
    }

    public function currentPlan(Shop $shop): ?Plan
    {
        return $this->current($shop)?->plan;
    }

    public function isActive(Shop $shop): bool
    {
        return $this->access->hasActiveSubscription($shop);
    }

    /**
     * The programmatic equivalent of RequireActiveSubscription middleware,
     * for call sites that aren't an HTTP request — a scheduled job or
     * console command that must not act on behalf of an unsubscribed
     * shop. Throws rather than returning a boolean so a caller can't
     * accidentally ignore the result. Only checks subscription status
     * (not whether the shop is still installed) — use
     * SubscriptionAccessService::assertFullAccess() for both checks
     * together.
     *
     * @throws \RuntimeException
     */
    public function assertActive(Shop $shop): void
    {
        if (! $this->isActive($shop)) {
            throw new \RuntimeException("Shop [{$shop->shopify_domain}] has no active subscription.");
        }
    }

    /**
     * Synchronizes local subscription state from a Shopify
     * `app_subscriptions/update` webhook payload — see
     * HandleSubscriptionUpdatedJob, the sole caller. Every transition is
     * recorded as a `SubscriptionEvent`, and `SubscriptionActivated` is
     * fired specifically on the transition INTO an active/trialing
     * status (not on every sync), since that's the one transition other
     * parts of the app (SyncPlanEntitlementsCache) need to react to.
     */
    public function syncFromWebhook(Shop $shop, array $payload): Subscription
    {
        $appSubscription = $payload['app_subscription'] ?? $payload;
        $shopifySubscriptionId = $appSubscription['admin_graphql_api_id'] ?? null;
        $status = strtolower($appSubscription['status'] ?? 'pending');
        $name = $appSubscription['name'] ?? null;

        return DB::transaction(function () use ($shop, $shopifySubscriptionId, $status, $name, $appSubscription) {
            $subscription = $this->resolveSubscriptionForSync($shop, $shopifySubscriptionId, $name);

            $fromStatus = $subscription->status;
            $fromPlanId = $subscription->plan_id;

            $subscription->update(array_filter([
                'shopify_subscription_id' => $shopifySubscriptionId ?: $subscription->shopify_subscription_id,
                'status' => $status,
                'shopify_payload' => $appSubscription,
                'cancelled_at' => in_array($status, ['cancelled', 'expired'], true) ? ($subscription->cancelled_at ?? now()) : $subscription->cancelled_at,
            ], fn ($v) => $v !== null));

            $this->recordEvent($shop, $subscription, $fromStatus, $status, $fromPlanId, $subscription->plan_id, 'shopify_webhook', $appSubscription);

            if (in_array($status, Subscription::ACTIVE_STATUSES, true) && ! in_array($fromStatus, Subscription::ACTIVE_STATUSES, true)) {
                event(new SubscriptionActivated($subscription->fresh()));
            }

            Log::info('Subscription synced from webhook', [
                'shop' => $shop->shopify_domain, 'from_status' => $fromStatus, 'to_status' => $status,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Finds the `Subscription` row this webhook update belongs to:
     * first by Shopify's own subscription ID (already synced once
     * before), then by the shop's most recent not-yet-linked row (the
     * `pending` record BillingService creates when initiating
     * checkout — it has no `shopify_subscription_id` yet, since
     * Shopify only assigns one after merchant confirmation). Falls back
     * to creating a row from the payload's plan `name` if neither
     * matches — covers a subscription created/changed directly in
     * Shopify Admin outside this app's own `/billing/subscribe` flow.
     */
    private function resolveSubscriptionForSync(Shop $shop, ?string $shopifySubscriptionId, ?string $name): Subscription
    {
        if ($shopifySubscriptionId) {
            $existing = Subscription::query()->where('shopify_subscription_id', $shopifySubscriptionId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $pending = Subscription::query()
            ->where('shop_id', $shop->id)
            ->whereNull('shopify_subscription_id')
            ->latest()
            ->first();

        if ($pending) {
            return $pending;
        }

        $plan = ($name ? $this->plans->all()->firstWhere('name', $name) : null)
            ?? $this->plans->default()
            ?? $this->plans->all()->first();

        return Subscription::query()->create([
            'shop_id' => $shop->id,
            'plan_id' => $plan?->id,
            'shopify_subscription_id' => $shopifySubscriptionId,
            'status' => 'pending',
        ]);
    }

    private function recordEvent(
        Shop $shop,
        Subscription $subscription,
        ?string $fromStatus,
        string $toStatus,
        ?int $fromPlanId,
        ?int $toPlanId,
        string $trigger,
        ?array $payload = null,
    ): SubscriptionEvent {
        return SubscriptionEvent::query()->create([
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_plan_id' => $fromPlanId,
            'to_plan_id' => $toPlanId,
            'trigger' => $trigger,
            'shopify_payload' => $payload,
        ]);
    }

    /** Called by BillingService when a merchant-initiated plan change is confirmed locally (before Shopify's webhook even arrives), so the event trail reflects intent immediately. */
    public function recordMerchantInitiatedChange(Shop $shop, Subscription $subscription, ?Plan $fromPlan, Plan $toPlan): void
    {
        $this->recordEvent($shop, $subscription, null, 'pending', $fromPlan?->id, $toPlan->id, 'merchant');
    }
}
