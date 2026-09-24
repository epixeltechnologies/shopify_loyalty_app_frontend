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
 * Fires on fulfillment status changes, line-item edits, financial
 * status changes, etc. — any of Shopify's "order updated" triggers.
 * Delegates the sync (upsert by shopify_order_id) to
 * ShopifyOrderSyncService, then re-attempts purchase-points accrual —
 * this is what awards points for an order that was unpaid at
 * `orders/create` time and has since transitioned to `paid` (a very
 * common real-world sequence: cash-on-delivery, manual payment capture,
 * a retried card charge). Safe to attempt on every update regardless of
 * whether points were already awarded — PointsAccrualService's
 * idempotency key is per (order, rule), so a re-attempt after points
 * already posted is a guaranteed no-op, not a duplicate award.
 */
class HandleOrderUpdatedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        $order = app(ShopifyOrderSyncService::class)->syncFromWebhook($shop, $webhook->payload);

        app(EventRecorder::class)->record($shop, 'order.updated', $order->customer, [
            'order_id' => $order->shopify_order_id,
            'financial_status' => $order->financial_status,
            'fulfillment_status' => $order->fulfillment_status,
        ]);

        $this->maybeAccruePoints($shop, $order);

        if (app(SubscriptionAccessService::class)->hasActiveSubscription($shop)) {
            app(ReferralQualificationService::class)->evaluateOrder($order);
        }
    }

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
