<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POINT SYSTEM: one row per customer (1:1) holding the current
     * denormalized point state — `balance` is a cache, never the source
     * of truth. The source of truth is the `point_transactions` ledger;
     * `PointsLedgerService::post()` is the ONLY code path allowed to
     * write to this table, always inside the same DB transaction as the
     * ledger insert it derives from, with `lockForUpdate` on this row to
     * make concurrent writers (two webhooks for the same customer)
     * serialize instead of racing.
     *
     * This is split out from `customers` (rather than columns on that
     * table, which was the earlier iteration of this schema) so that:
     *   (a) the extremely hot read/write path (points math on every
     *       order-paid webhook) never contends on the same row/index
     *       pages as the much colder customer-identity read path, and
     *   (b) point-balance-specific columns (pending expiry, next expiry
     *       date) can grow without widening every query that just wants
     *       a customer's name and email.
     */
    public function up(): void
    {
        Schema::create('points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->bigInteger('balance')->default(0);
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->unsignedBigInteger('lifetime_redeemed')->default(0);
            $table->unsignedBigInteger('lifetime_expired')->default(0);

            // Maintained by the points-expiry job so the storefront widget
            // can show "500 points expiring in 12 days" without a live
            // aggregate query over point_transactions on every page load.
            $table->unsignedBigInteger('pending_expiry_amount')->default(0);
            $table->date('next_expiry_date')->nullable();

            $table->timestamps();

            $table->unique(['shop_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points');
    }
};
