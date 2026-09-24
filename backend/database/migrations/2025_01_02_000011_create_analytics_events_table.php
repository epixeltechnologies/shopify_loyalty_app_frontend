<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ANALYTICS: the raw event feed — one row per domain occurrence
     * (customer.enrolled, points.earned, points.redeemed,
     * reward.redeemed, referral.completed, vip_tier.changed, ...),
     * written by App\Services\Analytics\EventRecorder at the point each
     * event happens. This is the WRITE path, optimized for fast
     * `INSERT`: minimal columns, one composite index, a `properties`
     * JSON blob for event-specific detail so adding a new event type
     * never requires a migration.
     *
     * `analytics_daily_snapshots` (below) is the READ path — a nightly
     * job aggregates this table into it. Dashboards query the snapshot
     * table, never this one directly, which keeps reporting queries fast
     * regardless of how large this table grows. At high volume this
     * table is a natural candidate for MySQL range partitioning by
     * month on `occurred_at` and/or periodic archival of rows older than
     * the aggregation window (e.g. 13 months) to cold storage — noted
     * here rather than implemented, since it depends on real traffic
     * patterns this app doesn't have yet.
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event_type'); // 'customer.enrolled', 'points.earned', 'reward.redeemed', ...
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['shop_id', 'event_type', 'occurred_at']);
            $table->index(['shop_id', 'occurred_at']); // "everything that happened in this window", for the nightly rollup job
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
