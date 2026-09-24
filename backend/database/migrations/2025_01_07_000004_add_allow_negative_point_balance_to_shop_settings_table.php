<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TENANT SYSTEM: per-shop override for whether a manual point
     * removal (PointAdjustmentService) may take a customer's balance
     * negative. Defaults to `false` (the safe default — a negative
     * balance is confusing to a shopper and to most merchants' mental
     * model of "points"); a shop that wants to allow it (e.g. to let
     * staff correct an over-award without first checking the current
     * balance) can opt in per `docs/POINTS_ENGINE.md#manual-adjustments`.
     */
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->boolean('allow_negative_point_balance')->default(false)->after('points_expiry_days');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('allow_negative_point_balance');
        });
    }
};
