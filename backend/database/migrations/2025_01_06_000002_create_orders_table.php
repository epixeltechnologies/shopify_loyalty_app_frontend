<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORDERS: a normalized, loyalty-relevant projection of a Shopify
     * order — deliberately NOT a full mirror of Shopify's order object
     * (no shipping address, no full payment details, no customer notes)
     * since none of that is needed for points calculation and storing
     * it would just be unnecessary sensitive data at rest. Every
     * monetary field is stored as integer cents (never a float), the
     * same convention `plans.price_monthly_cents` already uses
     * elsewhere in this schema, to avoid floating-point rounding errors
     * compounding across a shop's full order history.
     *
     * `customer_id` is nullable — an order can arrive for a Shopify
     * customer this app couldn't resolve/enroll (e.g. the shop was
     * already at its plan's customer limit at the moment of the
     * webhook — see HandleOrderCreatedJob) or for a guest checkout with
     * no customer record at all. The order is still recorded either way;
     * a future backfill/reconciliation job can re-attempt customer
     * association once capacity frees up.
     *
     * Idempotency: `(shop_id, shopify_order_id)` is UNIQUE.
     * ShopifyOrderSyncService always upserts on this pair — an
     * `orders/create` followed by an `orders/updated` (or a redelivery
     * of either) updates the same row rather than creating a duplicate.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('shopify_order_id');
            $table->string('order_number')->nullable(); // Shopify's merchant-facing "#1001" — display only, never used for lookups (shopify_order_id is)

            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('currency', 3);

            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->unsignedBigInteger('total_discounts_cents')->default(0);
            $table->unsignedBigInteger('total_tax_cents')->default(0);
            $table->unsignedBigInteger('total_shipping_cents')->default(0);

            $table->timestamp('shopify_created_at')->nullable();
            $table->timestamp('shopify_updated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();

            $table->unique(['shop_id', 'shopify_order_id']);
            // Customer's order history (a future points-history view) and shop-wide reporting by recency.
            $table->index(['shop_id', 'customer_id']);
            $table->index(['shop_id', 'shopify_created_at']);
            // Cancellation/refund-reversal sweeps (a future points-clawback job) scanning for newly-cancelled orders.
            $table->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
