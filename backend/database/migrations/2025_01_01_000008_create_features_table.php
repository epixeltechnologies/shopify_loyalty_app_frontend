<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BILLING: catalog of every gate-able capability (VIP tier slugs,
     * analytics depth, CSV export, API access, email notification tier).
     * `type` tells EntitlementService how to interpret a plan_features
     * row's `value` column.
     */
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'vip_tier.platinum', 'analytics.advanced', 'export.csv'
            $table->string('name');
            $table->string('group')->nullable(); // 'vip_tiers', 'analytics', 'reporting', 'notifications', 'api'
            $table->enum('type', ['boolean', 'limit', 'string'])->default('boolean');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
