<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Analytics\EventRecorder;
use App\Services\Points\PointReversalService;
use App\Services\Referrals\ReferralQualificationService;
use App\Services\Shopify\ShopifyOrderSyncService;

/**
 * Syncs the cancellation (`cancelled_at`/`cancel_reason`) onto the
 * existing `Order` row via ShopifyOrderSyncService::syncCancellation(),
 * then reverses any points that were already earned for it —
 * PointReversalService::reverseForCancellation() — as a compensating
 * `adjust` ledger entry, never by mutating the original `earn`
 * transaction(s). Idempotent: a redelivered `orders/cancelled` webhook
 * (or a retried job) reverses the same order's points at most once.
 */
class HandleOrderCancelledJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        $order = app(ShopifyOrderSyncService::class)->syncCancellation($shop, $webhook->payload);

        app(EventRecorder::class)->record($shop, 'order.cancelled', $order->customer, [
            'order_id' => $order->shopify_order_id,
            'cancel_reason' => $order->cancel_reason,
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
        ]);

        app(PointReversalService::class)->reverseForCancellation($order);
        app(ReferralQualificationService::class)->handleQualifyingOrderVoided($order, 'Order cancelled');
    }
}
