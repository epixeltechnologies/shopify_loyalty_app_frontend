<?php

namespace App\Services\Shopify;

use App\Models\Order;
use App\Models\Shop;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Turns a Shopify order webhook payload (`orders/create`,
 * `orders/updated`, `orders/cancelled`) into a normalized local `Order`
 * + `OrderLineItem` rows — the loyalty-relevant projection described in
 * the `orders` migration's comment, never a full mirror of Shopify's
 * order object.
 *
 * Idempotent by construction: every sync upserts on
 * `(shop_id, shopify_order_id)` (`OrderRepository::upsertByShopifyOrderId()`)
 * rather than inserting, so an `orders/create` followed by
 * `orders/updated` for the same order — or a redelivery of either —
 * always converges on one row, never a duplicate. Line items are
 * replaced wholesale on every sync (delete-then-recreate inside the
 * same transaction) rather than diffed, since Shopify doesn't expose a
 * stable "this line item was added/removed" webhook signal — the whole
 * order payload is always the source of truth for its current items.
 */
class ShopifyOrderSyncService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ShopifyCustomerSyncService $customerSync,
    ) {}

    public function syncFromWebhook(Shop $shop, array $payload): Order
    {
        return DB::transaction(function () use ($shop, $payload) {
            $customer = isset($payload['customer']) ? $this->customerSync->findOrEnroll($shop, $payload['customer']) : null;

            $order = $this->orders->upsertByShopifyOrderId($shop, (string) $payload['id'], [
                'shop_id' => $shop->id,
                'customer_id' => $customer?->id,
                'order_number' => isset($payload['order_number']) ? (string) $payload['order_number'] : ($payload['name'] ?? null),
                'financial_status' => $payload['financial_status'] ?? null,
                'fulfillment_status' => $payload['fulfillment_status'] ?? null,
                'currency' => $payload['currency'] ?? $shop->currency ?? 'USD',
                'subtotal_cents' => $this->toCents($payload['subtotal_price'] ?? 0),
                'total_cents' => $this->toCents($payload['total_price'] ?? 0),
                'total_discounts_cents' => $this->toCents($payload['total_discounts'] ?? 0),
                'total_tax_cents' => $this->toCents($payload['total_tax'] ?? 0),
                'total_shipping_cents' => $this->totalShippingCents($payload),
                'shopify_created_at' => $payload['created_at'] ?? null,
                'shopify_updated_at' => $payload['updated_at'] ?? null,
                'cancelled_at' => $payload['cancelled_at'] ?? null,
                'cancel_reason' => $payload['cancel_reason'] ?? null,
            ]);

            $this->syncLineItems($shop, $order, $payload['line_items'] ?? []);

            return $order->fresh(['lineItems']);
        });
    }

    /**
     * `orders/cancelled` carries the same payload shape as
     * `orders/create`/`orders/updated` (Shopify's full order object,
     * now with `cancelled_at` populated) — so cancellation is just
     * another sync, not a special code path. A dedicated method exists
     * only for callers (HandleOrderCancelledJob) to express intent
     * clearly, not because the underlying operation differs.
     */
    public function syncCancellation(Shop $shop, array $payload): Order
    {
        return $this->syncFromWebhook($shop, $payload);
    }

    private function syncLineItems(Shop $shop, Order $order, array $lineItems): void
    {
        $order->lineItems()->delete();

        foreach ($lineItems as $item) {
            $order->lineItems()->create([
                'shop_id' => $shop->id,
                'shopify_line_item_id' => isset($item['id']) ? (string) $item['id'] : null,
                'shopify_product_id' => isset($item['product_id']) ? (string) $item['product_id'] : null,
                'shopify_variant_id' => isset($item['variant_id']) ? (string) $item['variant_id'] : null,
                'title' => $item['title'] ?? 'Unknown item',
                'sku' => $item['sku'] ?? null,
                'vendor' => $item['vendor'] ?? null,
                'product_type' => $item['product_type'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'price_cents' => $this->toCents($item['price'] ?? 0),
                'total_discount_cents' => $this->toCents($item['total_discount'] ?? 0),
            ]);
        }
    }

    private function totalShippingCents(array $payload): int
    {
        $shippingLines = $payload['shipping_lines'] ?? [];

        return collect($shippingLines)->sum(fn ($line) => $this->toCents($line['price'] ?? 0));
    }

    /** Shopify sends monetary amounts as decimal strings (e.g. "49.99") — converted to integer cents, never stored as a float. See orders migration comment. */
    private function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
