<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SETTINGS: the "General" settings page's fields — see
     * docs/SETTINGS.md. Structured columns for everything with a fixed
     * shape (name/description/status/logo/colors), matching the task's
     * explicit "do not store everything blindly as JSON, use structured
     * database fields for important configuration." `messaging` remains
     * JSON deliberately — it's genuinely open-ended, merchant-defined
     * customer-facing copy (labels/CTAs) with no fixed key set this app
     * needs to query or validate individually, unlike everything else
     * here.
     *
     * `allow_manual_point_adjustments` is the "manual adjustment
     * permissions" point setting — this app has no staff/role-based
     * auth wired yet (see docs/NEXT_STEPS.md), so a shop-level on/off
     * toggle is the honest scope for "permissions" today; per-staff-
     * member permissions are a future milestone once real staff auth
     * exists.
     *
     * `timezone`/`currency` are deliberately NOT duplicated here —
     * `shops.timezone`/`shops.currency` already exist, synced from
     * Shopify's own shop info at OAuth time (see docs/AUTHENTICATION.md)
     * and already used throughout this app's timezone-aware logic
     * (VipQualificationService, DateRangeResolver). The General
     * settings page reads/displays those, it doesn't fork a second copy.
     */
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('program_name')->nullable()->after('shop_id');
            $table->text('program_description')->nullable()->after('program_name');
            $table->string('program_status', 20)->default('active')->after('program_description'); // active | paused
            $table->string('logo_url')->nullable()->after('program_status');
            $table->string('brand_secondary_color', 7)->nullable()->after('widget_primary_color');
            $table->json('messaging')->nullable()->after('points_earning_label');
            $table->boolean('allow_manual_point_adjustments')->default(true)->after('allow_negative_point_balance');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'program_name', 'program_description', 'program_status', 'logo_url',
                'brand_secondary_color', 'messaging', 'allow_manual_point_adjustments',
            ]);
        });
    }
};
