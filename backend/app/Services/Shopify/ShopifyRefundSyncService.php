<?php

namespace App\Services\Shopify;

use App\Models\Order;
use App\Models\Refund;
use App\Models\Shop;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Turns a Shopify `refunds/create` webhook payload into a normalized
 * local `Refund` row. Handles the explicitly required "refund arrives
 * before order" case: if the parent order isn't in `orders` yet, a
 * minimal placeholder row is upserted (via the same
 * `OrderRepository::upsertByShopifyOrderId()` every order sync uses) so
 * the refund can attach immediately — the real `orders/create`
 * webhook, whenever it arrives, fills in the rest via that same
 * upsert-by-`shopify_order_id` path without ever creating a second row.
 *
 * Does NOT calculate or reverse loyalty points — see `points_reversed_at`
 * on the `refunds` migration for where that plugs in later.
 */
class ShopifyRefundSyncService
{
    public function __construct(
        private readonly RefundRepositoryInterface $refunds,
        private readonly OrderRepositoryInterface $orders,
    ) {}

    public function syncFromWebhook(Shop $shop, array $payload): Refund
    {
        return DB::transaction(function () use ($shop, $payload) {
            $order = $this->resolveOrCreatePlaceholderOrder($shop, $payload);

            $amountCents = $this->totalRefundedCents($payload);
            $isPartial = $order->total_cents > 0 && $amountCents < $order->total_cents;

            return $this->refunds->upsertByShopifyRefundId($shop, (string) $payload['id'], [
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'amount_cents' => $amountCents,
                'currency' => $order->currency,
                'is_partial' => $isPartial,
                'note' => $payload['note'] ?? null,
                'shopify_created_at' => $payload['created_at'] ?? null,
            ]);
        });
    }

    /**
     * Refund payloads reference their order only by `order_id` (the
     * Shopify order ID, no embedded order object) — if that order
     * hasn't synced locally yet, a placeholder is upserted with the
     * fields available (currency, if inferable) and `total_cents = 0`.
     * `total_cents = 0` deliberately makes `isPartial` below evaluate
     * as `false` for a placeholder (a refund can't be judged "partial"
     * against an unknown total) — the correct partial/full
     * classification is recalculated once the real order data lands, by
     * whichever future job reconciles the two (out of scope for this
     * milestone; the data model supports it via the same
     * upsert-by-shopify-order-id path).
     */
    private function resolveOrCreatePlaceholderOrder(Shop $shop, array $payload): Order
    {
        $shopifyOrderId = (string) ($payload['order_id'] ?? '');

        $existing = $this->orders->findByShopifyOrderId($shop, $shopifyOrderId);
        if ($existing) {
            return $existing;
        }

        return $this->orders->upsertByShopifyOrderId($shop, $shopifyOrderId, [
            'shop_id' => $shop->id,
            'currency' => $payload['currency'] ?? $shop->currency ?? 'USD',
        ]);
    }

    private function totalRefundedCents(array $payload): int
    {
        $transactions = $payload['transactions'] ?? [];

        return (int) collect($transactions)->sum(fn ($t) => round(((float) ($t['amount'] ?? 0)) * 100));
    }
}
