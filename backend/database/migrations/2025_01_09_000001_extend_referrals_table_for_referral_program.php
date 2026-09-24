<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REFERRALS: extends the state machine from the original 4 values
     * (pending/completed/rewarded/expired) to the task's full 9-state
     * vocabulary (created/clicked/registered/pending/qualified/rewarded/
     * rejected/fraud_detected/cancelled) — `status` converted from
     * ENUM to VARCHAR, same portability rationale as every other
     * type/status column converted this way in this project.
     *
     * Adds the attribution model (`visitor_token` + a configurable
     * expiry — the "attribution window"), per-transition timestamps for
     * auditability, a separate `fraud_status`/`fraud_reasons` pair (a
     * referral can be `qualified` or even `rewarded` and SEPARATELY
     * flagged for fraud review — the two are orthogonal, not one
     * combined state, which is why fraud isn't just another `status`
     * value), and `reward_scheduled_at` (when the configurable reward
     * delay elapses and `ProcessReferralRewardsJob` may actually pay
     * out).
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('visitor_token')->nullable()->after('referral_code');
            $table->string('ip_address', 45)->nullable()->after('visitor_token');
            $table->string('user_agent')->nullable()->after('ip_address');

            $table->timestamp('clicked_at')->nullable()->after('completed_at');
            $table->timestamp('registered_at')->nullable()->after('clicked_at');
            $table->timestamp('qualified_at')->nullable()->after('registered_at');
            $table->timestamp('rejected_at')->nullable()->after('qualified_at');
            $table->timestamp('cancelled_at')->nullable()->after('rejected_at');

            $table->timestamp('attribution_expires_at')->nullable()->after('cancelled_at');
            $table->timestamp('reward_scheduled_at')->nullable()->after('attribution_expires_at');

            $table->unsignedInteger('qualifying_order_value_cents')->nullable()->after('qualifying_order_id');
            $table->string('rejection_reason')->nullable()->after('qualifying_order_value_cents');

            // Orthogonal to `status` — see class docblock.
            $table->string('fraud_status', 20)->default('none')->after('status');
            $table->json('fraud_reasons')->nullable()->after('fraud_status');

            $table->index('visitor_token');
            $table->index(['shop_id', 'fraud_status']);
            $table->unique(['shop_id', 'qualifying_order_id']); // a single order can never qualify more than one referral
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->string('status', 20)->default('created')->change();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'qualifying_order_id']);
            $table->dropIndex(['shop_id', 'fraud_status']);
            $table->dropIndex(['visitor_token']);
            $table->dropColumn([
                'visitor_token', 'ip_address', 'user_agent',
                'clicked_at', 'registered_at', 'qualified_at', 'rejected_at', 'cancelled_at',
                'attribution_expires_at', 'reward_scheduled_at', 'qualifying_order_value_cents',
                'rejection_reason', 'fraud_status', 'fraud_reasons',
            ]);
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->enum('status', ['pending', 'completed', 'rewarded', 'expired'])->default('pending')->change();
        });
    }
};
