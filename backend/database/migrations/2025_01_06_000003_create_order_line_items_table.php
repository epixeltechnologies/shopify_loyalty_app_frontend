<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORDER LINE ITEMS: one row per Shopify line item on an order.
     * `shop_id` is denormalized here (not just reachable via
     * `order_id`) — matching this app's consistent pattern (see
     * `referral_rewards`, `analytics_events`) of every table getting a
     * direct `shop_id` for `BelongsToShop`/`TenantScope` to apply
     * without a join, rather than relying on the parent relationship
     * alone for tenant isolation.
     *
     * `product_type` and `vendor` are stored directly from the order
     * webhook's line-item payload (both are included on every Shopify
     * order webhook at no extra API cost) specifically because a future
     * points rule keyed on product type/vendor needs them without a
     * separate Admin API call per line item. `collection` membership is
     * NOT stored here — Shopify's order webhook payload doesn't include
     * it, and fetching it would require a per-product API call this
     * table isn't the place to trigger; a future collection-based rule
     * would resolve it via `ShopifyGraphQLClient` (the existing,
     * centralized API abstraction) keyed off `shopify_product_id` at
     * evaluation time, not by denormalizing it here ahead of need.
     */
    public function up(): void
    {
        Schema::create('order_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('shopify_line_item_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();

            $table->string('title');
            $table->string('sku')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('price_cents'); // per-unit price
            $table->unsignedBigInteger('total_discount_cents')->default(0);

            $table->timestamps();

            $table->index(['shop_id', 'order_id']);
            // Future product/collection-based point rules query by product across orders.
            $table->index(['shop_id', 'shopify_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_items');
    }
};
