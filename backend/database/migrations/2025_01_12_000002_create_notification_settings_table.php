<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SETTINGS/NOTIFICATIONS: one row per (shop, notification type) —
     * the task's 9 notification types (welcome, points_earned,
     * reward_redeemed, birthday_reward, referral_reward, vip_upgraded,
     * vip_downgraded, points_expiring, reward_expiring). A typed row
     * per type (not one JSON blob covering all 9) so each type's
     * enabled/subject/message is individually queryable/validatable —
     * matching the "use structured database fields" instruction the
     * same way `notification_settings` per-type rows do here what
     * `analytics_daily_snapshots` per-metric rows do there.
     *
     * `message` stores merchant-authored content that WILL be
     * sanitized before it's ever saved (see
     * NotificationTemplateService::sanitize()) — this column is the
     * output of that sanitization, never raw, unsanitized HTML.
     */
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40); // 'welcome' | 'points_earned' | 'reward_redeemed' | 'birthday_reward' | 'referral_reward' | 'vip_upgraded' | 'vip_downgraded' | 'points_expiring' | 'reward_expiring'
            $table->boolean('enabled')->default(true);
            $table->string('subject')->nullable();
            $table->text('message')->nullable();

            $table->timestamps();

            $table->unique(['shop_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
