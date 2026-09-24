<?php

namespace App\Services\Points;

use App\Models\Order;
use App\Models\PointRule;

/**
 * POINT SYSTEM: the purchase-points rules engine —
 * `PointRule::type === 'points_per_dollar'`'s `config` JSON evaluated
 * against a synced `Order` (+ its `OrderLineItem`s, already normalized
 * by `App\Services\Shopify\ShopifyOrderSyncService` — see
 * docs/SYNCHRONIZATION.md) to produce a point amount. Deliberately
 * extensible: every rule field below is optional in `config`, so a
 * merchant's rule can start minimal ("1 point per dollar, no
 * exclusions") and grow more specific over time without a schema or
 * code change — a new config key is read the same way every existing
 * one is, with a safe default when absent.
 *
 * Supported `config` keys (all optional except `points_per_dollar`):
 *   - `points_per_dollar` (float) — e.g. 1 = 1 point per $1, 0.5 = 1 point per $2
 *   - `fixed_points_per_order` (int) — flat bonus instead of/alongside the per-dollar rate
 *   - `minimum_order_amount_cents` (int) — orders below this earn nothing from this rule
 *   - `exclude_discounts` (bool, default true) — points calculated on the discounted subtotal or the pre-discount amount
 *   - `exclude_tax` (bool, default true) — Shopify's `total_tax` never counts toward eligible amount when true
 *   - `exclude_shipping` (bool, default true) — same, for `total_shipping`
 *   - `included_product_ids` / `excluded_product_ids` (string[], Shopify product IDs) — line-item-level filtering
 *   - `included_collection_ids` / `excluded_collection_ids` (string[]) — NOTE: collection membership isn't
 *     denormalized onto `order_line_items` (see that migration's comment) — these keys are accepted and
 *     validated by the rule config schema for forward-compatibility, but are not yet evaluated here; a
 *     collection-aware evaluation would resolve membership via `ShopifyGraphQLClient` at this point, not
 *     by widening the order-sync schema ahead of need. See docs/POINTS_ENGINE.md.
 */
class PointCalculationService
{
    public function calculateForOrder(PointRule $rule, Order $order): int
    {
        $config = $rule->config;

        $eligibleAmountCents = $this->eligibleAmountCents($config, $order);

        if (isset($config['minimum_order_amount_cents']) && $order->total_cents < (int) $config['minimum_order_amount_cents']) {
            return 0;
        }

        $perDollarPoints = 0;
        if (isset($config['points_per_dollar']) && $eligibleAmountCents > 0) {
            $eligibleDollars = $eligibleAmountCents / 100;
            $perDollarPoints = (int) floor($eligibleDollars * (float) $config['points_per_dollar']);
        }

        $fixedPoints = (int) ($config['fixed_points_per_order'] ?? 0);

        return max(0, $perDollarPoints + $fixedPoints);
    }

    /**
     * Starts from the order subtotal (Shopify's `subtotal_price` — the
     * item total AFTER line-item discounts, BEFORE tax/shipping — this
     * is already "discounts excluded, tax/shipping excluded" by
     * definition of what `subtotal_price` means) and adjusts only when
     * a rule explicitly opts OUT of the default exclusions, or applies
     * product-level inclusion/exclusion filtering.
     */
    private function eligibleAmountCents(array $config, Order $order): int
    {
        $excludeDiscounts = $config['exclude_discounts'] ?? true;
        $excludeTax = $config['exclude_tax'] ?? true;
        $excludeShipping = $config['exclude_shipping'] ?? true;

        // subtotal_cents is already post-discount, pre-tax, pre-shipping.
        $amount = $order->subtotal_cents;

        if (! $excludeDiscounts) {
            // Opting IN to counting discounted-away value means crediting
            // back what the discount removed, i.e. calculating against
            // the pre-discount subtotal.
            $amount += $order->total_discounts_cents;
        }

        if (! $excludeTax) {
            $amount += $order->total_tax_cents;
        }

        if (! $excludeShipping) {
            $amount += $order->total_shipping_cents;
        }

        if ($this->hasProductFiltering($config)) {
            $amount = $this->filterByProduct($config, $order);
        }

        return max(0, $amount);
    }

    private function hasProductFiltering(array $config): bool
    {
        return ! empty($config['included_product_ids']) || ! empty($config['excluded_product_ids']);
    }

    /**
     * When product-level filtering is configured, the eligible amount
     * is recalculated from the actual line items that pass the filter
     * (quantity × price, discounts/tax/shipping filtering doesn't apply
     * at this granularity — Shopify doesn't allocate order-level
     * tax/shipping per line item in the webhook payload) rather than
     * the order-level subtotal, since only some of the order's items
     * may be eligible.
     */
    private function filterByProduct(array $config, Order $order): int
    {
        $included = array_map('strval', $config['included_product_ids'] ?? []);
        $excluded = array_map('strval', $config['excluded_product_ids'] ?? []);

        return $order->lineItems
            ->filter(function ($item) use ($included, $excluded) {
                $productId = (string) $item->shopify_product_id;

                if (! empty($included) && ! in_array($productId, $included, true)) {
                    return false;
                }

                return ! (! empty($excluded) && in_array($productId, $excluded, true));
            })
            ->sum(fn ($item) => ($item->price_cents * $item->quantity) - $item->total_discount_cents);
    }
}
