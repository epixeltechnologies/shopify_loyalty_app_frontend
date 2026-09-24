<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POINT SYSTEM: append-only ledger — the single source of truth for
     * every point balance. `points.balance` is derived from this table
     * and must never be mutated outside PointsLedgerService. Rows are
     * never updated or deleted in normal operation (a correction is a
     * new `adjust` row, not an edit to a past row) — this is what makes
     * the ledger auditable and lets `points.balance` be trusted as a
     * cache rather than treated with suspicion.
     *
     * Indexed for the two access patterns that matter at scale: "this
     * customer's history" (paginated, newest first) and "this shop's
     * activity in a date range" (reporting/analytics rollups). No
     * `updated_at` — see the model's `UPDATED_AT = null`, since a ledger
     * row is never updated.
     */
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_rule_id')->nullable()->constrained('point_rules')->nullOnDelete();

            $table->enum('direction', ['earn', 'redeem', 'expire', 'adjust']);
            $table->bigInteger('points'); // signed: positive for earn, negative for redeem/expire/adjust-down
            $table->bigInteger('balance_after'); // denormalized snapshot for cheap historical display without replaying the ledger

            $table->string('source'); // 'order', 'referral', 'signup_bonus', 'reward_redemption', 'manual', 'expiration'
            $table->nullableMorphs('source_reference'); // polymorphic link to the originating record (RewardRedemption, Referral, Order reference, ...)

            $table->text('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'customer_id', 'created_at']);
            $table->index(['shop_id', 'direction', 'created_at']); // shop-wide reporting rollups (points issued/redeemed per period)
            $table->index('expires_at'); // scanned by the daily expiry job
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
