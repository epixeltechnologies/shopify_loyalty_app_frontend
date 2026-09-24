<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VIP: append-only log of every tier change. `customers.vip_tier_id`
     * only holds the *current* tier — this table is what answers "when
     * did this customer become Gold" or "how many customers upgraded to
     * Platinum last quarter" for analytics/reporting. Written by
     * VipTierService::evaluate() every time it changes a customer's
     * tier, in the same request/job as the change itself. No
     * `updated_at`: a history row is a fact about a point in time and is
     * never revised.
     */
    public function up(): void
    {
        Schema::create('customer_vip_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_vip_tier_id')->nullable()->constrained('vip_tiers')->nullOnDelete();
            $table->foreignId('to_vip_tier_id')->nullable()->constrained('vip_tiers')->nullOnDelete();

            $table->enum('direction', ['upgrade', 'downgrade', 'initial']);
            $table->unsignedInteger('lifetime_points_at_change');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vip_history');
    }
};
