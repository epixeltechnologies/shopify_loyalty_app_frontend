<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REWARD REDEMPTIONS: extends the redemption record with everything
     * the task's audit/security requirements ask for. `status` is
     * converted from `ENUM` to `VARCHAR` to add `failed` (a redemption
     * whose Shopify discount creation failed and was compensated with a
     * point refund — see RewardRedemptionService/FulfillRewardRedemptionJob)
     * without a non-portable enum-modification statement — same
     * rationale as `point_rules.type`/`rewards.type`. The existing
     * `fulfilled` value is renamed to `completed` to match the task's
     * status vocabulary exactly; nothing outside this table's own
     * factory referenced the old value (verified before this rename —
     * see docs/REWARDS_ENGINE.md), so this is safe.
     *
     * `balance_before`/`balance_after` are stored directly on this row
     * (not just derivable by joining to the `point_transactions` row
     * they correspond to) because the task explicitly lists them as
     * required fields of the redemption record itself — a merchant
     * viewing redemption history shouldn't need to cross-reference the
     * ledger to see what a customer's balance was at the time.
     *
     * `shopify_discount_id` is the GraphQL discount-code node ID
     * (`gid://shopify/DiscountCodeNode/...`) from Shopify's current
     * Admin API. The pre-existing `shopify_price_rule_id` column (REST
     * PriceRule API — deprecated, never actually populated by any real
     * code) is left in place for backward compatibility but is not
     * written to by this milestone's `ShopifyDiscountService`.
     */
    public function up(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->bigInteger('balance_before')->nullable()->after('points_spent');
            $table->bigInteger('balance_after')->nullable()->after('balance_before');
            $table->string('idempotency_key')->nullable()->after('balance_after');
            $table->string('shopify_discount_id')->nullable()->after('shopify_discount_code');
            $table->text('failure_reason')->nullable()->after('shopify_discount_id');
            $table->json('metadata')->nullable()->after('failure_reason');

            $table->unique(['shop_id', 'idempotency_key']);
        });

        DB::table('reward_redemptions')->where('status', 'fulfilled')->update(['status' => 'completed']);

        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'idempotency_key']);
            $table->dropColumn(['balance_before', 'balance_after', 'idempotency_key', 'shopify_discount_id', 'failure_reason', 'metadata']);
        });

        DB::table('reward_redemptions')->where('status', 'completed')->update(['status' => 'fulfilled']);

        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'fulfilled', 'cancelled', 'expired'])->default('pending')->change();
        });
    }
};
