<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POINT SYSTEM: completes the lifetime-stats set on `points` (earned/
     * redeemed/expired already existed) with `lifetime_adjusted` — the
     * net sum of every manual/administrative adjustment, tracked
     * separately from `lifetime_earned` specifically so "how many points
     * has this customer actually earned through the program" stays a
     * meaningful number uncontaminated by support-team corrections.
     * Signed (can be negative — an admin removing points nets negative),
     * unlike the other three lifetime counters which are magnitudes.
     *
     * `last_transaction_at` is denormalized from `point_transactions.created_at`
     * for the "sort customers by loyalty activity" query
     * (`docs/POINTS_ENGINE.md`) without a join/subquery.
     */
    public function up(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->bigInteger('lifetime_adjusted')->default(0)->after('lifetime_expired');
            $table->timestamp('last_transaction_at')->nullable()->after('next_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->dropColumn(['lifetime_adjusted', 'last_transaction_at']);
        });
    }
};
