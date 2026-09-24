<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERFORMANCE (hardening audit): `reward_redemptions` had only a
     * single-column index on `customer_id` (from `foreignId()
     * ->constrained()`) and a `(shop_id, status)` composite — neither
     * covers the actual hot-path query
     * (`RewardRedemptionRepository::paginateForCustomer()`, used by
     * both the admin customer-detail page and the storefront
     * `/rewards/redemptions` endpoint): filter by customer, order by
     * `created_at` descending. Without a covering composite index,
     * MySQL can use the `customer_id` index to filter but then needs a
     * filesort for the ORDER BY — cheap at today's data volume, but
     * exactly the kind of query that degrades as redemption history
     * grows per customer. `point_transactions` already has this exact
     * index shape (`shop_id, customer_id, created_at`) for the
     * equivalent points-history query; this brings redemptions in line
     * with that established pattern rather than leaving one hot path
     * unindexed while its sibling is covered.
     */
    public function up(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->index(['shop_id', 'customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'customer_id', 'created_at']);
        });
    }
};
