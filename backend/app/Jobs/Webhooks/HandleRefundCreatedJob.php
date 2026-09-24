<?php

namespace App\Jobs\Webhooks;

use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Analytics\EventRecorder;
use App\Services\Points\PointReversalService;
use App\Services\Referrals\ReferralQualificationService;
use App\Services\Shopify\ShopifyRefundSyncService;

/**
 * Records the refund event (Shopify's `refunds/create` topic — not
 * `orders/refunded`, which is not a real Shopify webhook topic).
 * Delegates the sync — including the "refund arrives before its order"
 * case (a placeholder `Order` row is upserted so the refund can attach
 * immediately) — to ShopifyRefundSyncService, then reverses the
 * proportional share of previously-earned points via
 * PointReversalService::reverseForRefund(), which correctly handles
 * multiple partial refunds on the same order without over-reversing —
 * see that method's docblock. Idempotent per refund
 * (`refund_reversal:{shop_id}:{refund_id}`) regardless of webhook
 * redelivery.
 */
class HandleRefundCreatedJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        if (! $shop) {
            return;
        }

        $refund = app(ShopifyRefundSyncService::class)->syncFromWebhook($shop, $webhook->payload);

        app(EventRecorder::class)->record($shop, 'refund.created', $refund->order->customer ?? null, [
            'order_id' => $refund->order->shopify_order_id,
            'refund_id' => $refund->shopify_refund_id,
            'amount' => $refund->amount_cents / 100,
            'is_partial' => $refund->is_partial,
        ]);

        app(PointReversalService::class)->reverseForRefund($refund);

        // Referral qualification requires "order not fully refunded" —
        // a partial refund alone doesn't disqualify an already-
        // qualified/rewarded referral, only a full refund of the
        // qualifying order does.
        if (! $refund->is_partial) {
            app(ReferralQualificationService::class)->handleQualifyingOrderVoided($refund->order, 'Order fully refunded');
        }
    }
}
