<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VIP: shop-defined tiers (Silver/Gold/Platinum by default). Created
     * before `customers` because customers.vip_tier_id references this
     * table. Which tier *slugs* a shop may create is gated by the
     * `vip_tier.*` features on its plan (EntitlementService), not by
     * anything in this table itself.
     */
    public function up(): void
    {
        Schema::create('vip_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug'); // 'silver' | 'gold' | 'platinum' | custom
            $table->unsignedInteger('threshold_points')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('perks')->nullable(); // e.g. { "points_multiplier": 1.5, "free_shipping": true }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'slug']);
            $table->index(['shop_id', 'threshold_points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_tiers');
    }
};
