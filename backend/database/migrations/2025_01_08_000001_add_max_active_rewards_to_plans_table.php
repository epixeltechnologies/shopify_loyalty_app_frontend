<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BILLING: a distinct plan limit for the `Reward` catalog (what
     * points can be redeemed for), kept as its own first-class nullable
     * column exactly like `max_active_customers`/`max_active_point_rules`
     * — see that migration's comment for why numeric limits live here
     * rather than in `plan_features`.
     *
     * NOTE on a naming collision from an earlier milestone: the points-
     * engine session added `EntitlementService::canCreateRewardCampaign()`
     * as an alias for `canAddPointRule()`, under the assumption that
     * "reward campaign" meant a point-earning rule. This milestone
     * clarifies that the task/product vocabulary's "reward campaign"
     * actually means the `Reward` (redeemable catalog) entity being
     * built out here. Rather than repoint the existing alias (which
     * would silently change what an already-shipped, already-tested
     * method means), this migration adds a distinct, correctly-scoped
     * limit — `canAddReward()`/`max_active_rewards` — and the older
     * alias is left exactly as it was, now documented as a known
     * naming quirk rather than corrected in place. See
     * docs/REWARDS_ENGINE.md.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_active_rewards')->nullable()->after('max_active_point_rules');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_active_rewards');
        });
    }
};
