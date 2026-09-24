<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SETTINGS: the shop-level VIP program switch — see
     * docs/SETTINGS.md. Deliberately NOT where qualification method,
     * evaluation period, or benefits live — those are already
     * correctly per-TIER config on `vip_tiers` (a shop can have tiers
     * with DIFFERENT qualification methods/periods simultaneously, so a
     * single shop-level value for them would be actively wrong, not
     * just redundant). This table only holds what's genuinely shop-
     * wide: whether the VIP program is on at all, and whether upgrade/
     * downgrade notifications should fire (the "tier notifications"
     * setting — the actual subject/message content lives in
     * `notification_settings`, keyed by type, not duplicated here).
     * DEFAULT: `enabled` defaults to `true`, not `false` — VIP tiers
     * already work correctly without this settings row existing at all
     * (VipEvaluationService treats "no row yet" as enabled). Defaulting
     * the column itself to `false` would mean the FIRST time a row
     * auto-vivifies for a shop (e.g. simply opening the VIP settings
     * page, which calls `firstOrCreate()`) silently disables VIP
     * evaluation for any shop already using it — exactly the kind of
     * "do not break existing functionality" regression this column
     * must not cause.
     */
    public function up(): void
    {
        Schema::create('vip_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->boolean('enabled')->default(true);
            $table->boolean('notify_on_upgrade')->default(true);
            $table->boolean('notify_on_downgrade')->default(true);

            $table->timestamps();

            $table->unique('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_settings');
    }
};
