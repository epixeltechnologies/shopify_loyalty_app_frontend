<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VIP: adds the qualification-audit fields the task's "Tier
     * History" section requires beyond what the original schema
     * tracked. `effective_date` is deliberately separate from
     * `created_at` — for a scheduled (batch) evaluation, "when this row
     * was written" and "when the tier change is considered effective"
     * are conceptually distinct even though this milestone sets them
     * to the same instant in every current code path; keeping them
     * separate columns now avoids a breaking migration later if a
     * future backdating feature needs them to differ.
     */
    public function up(): void
    {
        Schema::table('customer_vip_history', function (Blueprint $table) {
            $table->string('qualification_reason')->nullable()->after('direction');
            $table->string('evaluation_period', 20)->nullable()->after('qualification_reason');
            $table->timestamp('effective_date')->nullable()->after('lifetime_points_at_change');
        });
    }

    public function down(): void
    {
        Schema::table('customer_vip_history', function (Blueprint $table) {
            $table->dropColumn(['qualification_reason', 'evaluation_period', 'effective_date']);
        });
    }
};
