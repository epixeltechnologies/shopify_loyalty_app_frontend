<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ANALYTICS: pre-aggregated daily metrics, one row per
     * (shop, date, metric) — the table every dashboard/report query
     * actually reads. Populated nightly by App\Services\Analytics\SnapshotBuilder
     * from `analytics_events`. Basic vs. advanced analytics
     * (`analytics.basic` / `analytics.advanced` plan features) is a
     * route-level gate over this same table — advanced tier adds derived
     * queries (cohort/retention), not a different schema.
     */
    public function up(): void
    {
        Schema::create('analytics_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('metric'); // 'points_issued', 'points_redeemed', 'active_members', 'referrals_completed', ...
            $table->decimal('value', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'date', 'metric']);
            $table->index(['shop_id', 'metric', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_snapshots');
    }
};
