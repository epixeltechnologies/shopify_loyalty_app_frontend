<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REWARDS: extends the redeemable catalog with everything the
     * redemption engine needs — restriction/eligibility config, usage
     * limits, and a date window. `type` is converted from `ENUM` to
     * `VARCHAR` (same rationale as `point_rules.type` in the points-
     * engine milestone — see that migration's comment): the task
     * explicitly asks for "future reward types addable without
     * rewriting the core redemption system," and a database enum is
     * the opposite of that. New rule config is stored in JSON columns
     * (`included_product_ids` etc.) mirroring `point_rules.config`'s
     * shape, kept as separate typed columns here rather than one
     * catch-all JSON blob because these specific fields are directly
     * named in the task's reward-configuration spec (queryable/
     * validatable individually), unlike `point_rules.config` which is
     * genuinely rule-type-dependent and has no fixed shape.
     */
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('description');
            $table->unsignedInteger('min_purchase_amount_cents')->nullable()->after('value');
            $table->unsignedInteger('max_discount_amount_cents')->nullable()->after('min_purchase_amount_cents'); // caps a percentage discount's payout — n/a to fixed/free-shipping
            $table->json('included_product_ids')->nullable()->after('max_discount_amount_cents');
            $table->json('excluded_product_ids')->nullable()->after('included_product_ids');
            $table->json('included_collection_ids')->nullable()->after('excluded_product_ids');
            $table->json('excluded_collection_ids')->nullable()->after('included_collection_ids');
            // { "type": "all" } | { "type": "vip_tier_minimum", "vip_tier_id": N } | { "type": "specific_customers", "customer_ids": [...] }
            $table->json('customer_eligibility')->nullable()->after('excluded_collection_ids');
            $table->unsignedInteger('max_total_redemptions')->nullable()->after('customer_eligibility'); // null = unlimited; stock_limit already covers a simpler "N available" case, this is the explicit redemption-count cap
            $table->unsignedInteger('max_redemptions_per_customer')->nullable()->after('max_total_redemptions'); // e.g. 1 = "one redemption per customer"
            $table->timestamp('starts_at')->nullable()->after('max_redemptions_per_customer');
            $table->timestamp('ends_at')->nullable()->after('starts_at');

            $table->index(['shop_id', 'status', 'starts_at', 'ends_at']);
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'status', 'starts_at', 'ends_at']);
            $table->dropColumn([
                'image_url', 'min_purchase_amount_cents', 'max_discount_amount_cents',
                'included_product_ids', 'excluded_product_ids', 'included_collection_ids', 'excluded_collection_ids',
                'customer_eligibility', 'max_total_redemptions', 'max_redemptions_per_customer', 'starts_at', 'ends_at',
            ]);
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->enum('type', ['percentage_discount', 'fixed_discount', 'free_shipping', 'gift'])->change();
        });
    }
};
