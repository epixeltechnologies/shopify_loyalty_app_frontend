<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POINT SYSTEM: a rule that earns customers points (e.g. "1 point
     * per $1 spent", "500 points on signup", "referral bonus", "birthday
     * bonus"). Distinct from `rewards`, which define what points can be
     * *spent* on. Creating a rule is capped by
     * EntitlementService::canAddPointRule() (`plans.max_active_point_rules`).
     */
    public function up(): void
    {
        Schema::create('point_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['points_per_dollar', 'signup_bonus', 'referral_bonus', 'birthday_bonus', 'custom']);
            $table->json('config'); // e.g. { "points_per_dollar": 1 } or { "bonus_points": 500 }
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_rules');
    }
};
