<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ANALYTICS: tracks one row per requested report export — the
     * async flow the task requires (request -> queue -> generate ->
     * store -> temporary signed URL -> expire), never a CSV generated
     * synchronously inside an HTTP request/response cycle.
     *
     * `file_path` points into local/private disk storage (never a
     * public disk) — the only way to actually retrieve the file is
     * through `GET /analytics/exports/{export}/download`, which issues
     * a short-lived Laravel signed URL rather than exposing a
     * permanent public path. See ExportService/docs/ANALYTICS.md.
     */
    public function up(): void
    {
        Schema::create('analytics_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('report_type'); // 'customer_growth', 'points', 'rewards', 'redemptions', 'referrals', 'vip', 'engagement'
            $table->string('status', 20)->default('pending'); // pending -> processing -> completed | failed
            $table->json('filters')->nullable(); // the date range + any report-specific filters this export was requested with
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_exports');
    }
};
