<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VIP: extends `vip_tiers` with the qualification-engine
     * configuration surface. `qualification_method` selects WHICH of
     * the three threshold columns below is actually evaluated for this
     * tier — kept as separate, clearly-named columns (rather than one
     * generic "threshold" reused across methods) so the admin UI and
     * any reporting can display "minimum spend: $500" without needing
     * to know which method is active; a future qualification method
     * is one new nullable column plus one new case in
     * VipQualificationService's evaluation match, never a rename or
     * migration of existing data.
     *
     * `evaluation_period` + `rolling_period_days` make the qualification
     * WINDOW configurable independently of the method — see
     * docs/VIP_TIERS.md for the timezone-handling rationale
     * (`shops.timezone`, already populated from Shopify's shop info at
     * OAuth time, is the source of truth for calendar/rolling boundary
     * calculations, never server-local time or UTC-by-assumption).
     */
    public function up(): void
    {
        Schema::table('vip_tiers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('qualification_method', 30)->default('points_earned')->after('slug');
            $table->unsignedInteger('minimum_spend_cents')->nullable()->after('threshold_points');
            $table->unsignedInteger('minimum_orders')->nullable()->after('minimum_spend_cents');
            $table->string('evaluation_period', 20)->default('lifetime')->after('minimum_orders');
            $table->unsignedSmallInteger('rolling_period_days')->nullable()->after('evaluation_period');
            $table->timestamp('starts_at')->nullable()->after('rolling_period_days');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('vip_tiers', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'qualification_method', 'minimum_spend_cents', 'minimum_orders',
                'evaluation_period', 'rolling_period_days', 'starts_at', 'ends_at',
            ]);
        });
    }
};
