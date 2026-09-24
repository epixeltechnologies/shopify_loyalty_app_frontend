<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REFERRALS: one row per shop (1:1), holding every merchant-
     * configurable referral-program setting in one place — mirrors
     * `shop_settings`'s split-by-concern rationale (see that
     * migration's comment): the referral program has enough of its own
     * settings to warrant a dedicated table rather than widening
     * `shop_settings` further, and it maps to exactly one admin page
     * ("Referral Settings" per the task's React-pages list).
     *
     * Deliberately NOT modeled as a `point_rules` row (even though
     * `point_rules.type` already has a `referral_bonus` value from an
     * earlier milestone) — a referral program needs far more
     * configuration surface (minimum order value, first-purchase
     * requirement, reward delay, attribution window, per-customer cap)
     * than a `PointRule`'s single `config` JSON comfortably models as
     * one cohesive, independently-versioned settings resource. See
     * docs/REFERRAL_PROGRAM.md.
     */
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('referrer_reward_points')->nullable();
            $table->unsignedInteger('referee_reward_points')->nullable();
            $table->unsignedInteger('minimum_qualifying_order_cents')->nullable();
            $table->boolean('require_first_purchase')->default(true);
            $table->unsignedSmallInteger('reward_delay_days')->default(0);
            $table->unsignedSmallInteger('attribution_window_days')->default(30);
            $table->unsignedInteger('max_referrals_per_customer')->nullable(); // null = unlimited

            $table->timestamps();
            $table->softDeletes();

            $table->unique('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
