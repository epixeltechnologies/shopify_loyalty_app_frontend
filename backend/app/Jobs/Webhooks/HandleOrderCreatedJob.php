<?php

namespace App\Jobs\Webhooks;

use App\Models\Order;
use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Analytics\EventRecorder;
use App\Services\Billing\SubscriptionAccessService;
use App\Services\Points\PointsAccrualService;
use App\Services\Referrals\ReferralQualificationService;
use App\Services\Shopify\ShopifyOrderSyncService;

/**
 * Syncs the order (customer resolution/enrollment, Order +
 * OrderLineItem upsert — see ShopifyOrderSyncService/docs/SYNCHRONIZATION.md),
 * then — for a shop with an active subscription and a customer
 * successfully associated — awards purchase points via
 * PointsAccrualService when the order is already paid. Points are
 * deliberately NOT awarded for an unpaid order (`financial_status`
 * other than `paid`) here; `HandleOrderUpdatedJob` re-attempts accrual
 * on every subsequent update, so an order that starts unpaid and later
 * transitions to paid still earns points, exactly once (idempotency
 * key includes the order and rule, not the webhook delivery).
 */
class HandleOrderCreatedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return; // unresolvable tenant — nothing safe to do with this event
        }

        $order = app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, $webhook->payload);

        app(EventRecorder::class)->record($shop, 'order.created', $order->customer, [
            'order_id' => $order->shopify_order_id,
            'total_price' => $order->total_cents / 100,
            'currency' => $order->currency,
        ]);

        $this->maybeAccruePoints($shop, $order);

        if (app(SubscriptionAccessService::class)->hasActiveSubscription($shop)) {
            app(ReferralQualificationService::class)->evaluateOrder($order);
        }
    }

    /**
     * No active subscription => no points processing at all (this
     * app's "no free plan" rule applies to loyalty processing exactly
     * like every other feature — see
     * App\Services\Billing\SubscriptionAccessService) — the order is
     * still synced/recorded above regardless, since losing order
     * history isn't an acceptable side effect of a lapsed subscription,
     * only losing loyalty PROCESSING is.
     */
    private function maybeAccruePoints(Shop $shop, Order $order): void
    {
        if (! $order->customer || $order->financial_status !== 'paid') {
            return;
        }

        if (! app(SubscriptionAccessService::class)->hasActiveSubscription($shop)) {
            return;
        }

        app(PointsAccrualService::class)->accrueForOrder($order->customer, $order);
    }
}
