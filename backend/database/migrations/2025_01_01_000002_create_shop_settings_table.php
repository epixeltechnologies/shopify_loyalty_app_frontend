<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TENANT SYSTEM: one row per shop (1:1), split out from `shops`
     * rather than a JSON column on `shops` itself so individual settings
     * are indexable/queryable (e.g. "which shops have the widget
     * enabled") and so this table can grow new settings columns without
     * migrating the high-traffic `shops` table. `shops` is read on
     * nearly every request (tenant resolution); `shop_settings` is read
     * only where settings actually matter (widget rendering, points
     * expiry jobs, notification dispatch).
     */
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->boolean('widget_enabled')->default(true);
            $table->string('widget_primary_color', 7)->default('#1a1a1a'); // hex, validated at the FormRequest layer
            $table->unsignedSmallInteger('points_expiry_days')->nullable(); // null = points never expire
            $table->string('notification_email')->nullable();
            $table->string('points_earning_label')->default('points'); // merchant-facing terminology customization
            $table->json('extra')->nullable(); // low-traffic/experimental settings that don't yet warrant a column

            $table->timestamps();
            $table->softDeletes();

            $table->unique('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
