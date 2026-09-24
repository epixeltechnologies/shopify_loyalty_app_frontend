<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BILLING: the two purchasable plans (Starter, Professional), or any
     * future plan — rows here, never hard-coded in application code. See
     * docs/ARCHITECTURE.md#entitlement-architecture. `max_active_customers`
     * and `max_active_point_rules` are kept as first-class nullable
     * integer columns (null = unlimited) since every plan has exactly
     * these two numeric limits and they're read on the hot path of
     * nearly every mutating request; everything else (VIP tiers,
     * analytics depth, exports, API access, notification tier) is
     * modeled as a `feature` via `plan_features`.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // 'starter', 'professional'
            $table->string('name');
            $table->string('shopify_plan_handle')->nullable()->unique(); // Shopify Managed Pricing handle
            $table->text('description')->nullable();

            $table->unsignedInteger('price_monthly_cents')->default(0);
            $table->string('currency', 3)->default('USD');

            $table->unsignedInteger('max_active_customers')->nullable(); // null = unlimited
            $table->unsignedInteger('max_active_point_rules')->nullable(); // null = unlimited

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
