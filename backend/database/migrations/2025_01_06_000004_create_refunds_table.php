<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REFUNDS: one row per Shopify `refunds/create` event — supports
     * both partial and full refunds (`is_partial`, derived and stored
     * explicitly at write time for cheap querying rather than
     * recomputed from `orders.total_cents` on every read).
     *
     * `points_reversed_at` and `point_transaction_id` are included NOW,
     * ahead of the points engine that will use them (Prompt 11) —
     * deliberately nullable and unused by anything in this milestone.
     * This is the "architecture must allow future points reversal"
     * requirement: when accrual exists, a refund-processing job can
     * post a compensating `adjust` ledger entry
     * (App\Services\Points\PointsLedgerService — already built, see
     * docs/ARCHITECTURE.md#loyalty-domain) and record it here, without
     * a schema change at that point. `point_transaction_id` has no
     * foreign key constraint (point_transactions can predate this
     * table's data or reference a different shop's row structure over
     * time) — it's a soft reference by design, resolved at read time by
     * the future points-reversal job, not enforced at the DB level.
     *
     * Idempotency: `(shop_id, shopify_refund_id)` is UNIQUE — a
     * redelivered `refunds/create` webhook updates the same row.
     *
     * `order_id` is nullable specifically for "refund arrives before
     * order" (see ShopifyRefundSyncService) — the order this refund
     * belongs to may not exist yet locally; a minimal placeholder
     * `Order` row is upserted so the refund can attach immediately, and
     * the real `orders/create` webhook (whenever it arrives) fills in
     * the rest via the same upsert-by-`shopify_order_id` path.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('shopify_refund_id');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->boolean('is_partial')->default(false);
            $table->text('note')->nullable();

            $table->timestamp('shopify_created_at')->nullable();

            // Reserved for the future points-reversal job — see class docblock.
            $table->timestamp('points_reversed_at')->nullable();
            $table->unsignedBigInteger('point_transaction_id')->nullable();

            $table->timestamps();

            $table->unique(['shop_id', 'shopify_refund_id']);
            $table->index(['shop_id', 'order_id']);
            $table->index('points_reversed_at'); // future sweep: "refunds not yet reversed in points"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
