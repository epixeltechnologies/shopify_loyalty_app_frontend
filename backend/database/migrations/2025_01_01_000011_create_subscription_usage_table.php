<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BILLING: denormalized, fast-read usage counters (active customers,
     * active point rules) maintained transactionally by
     * UsageTrackerService alongside the write that changes them, so a
     * limit check on the hot path (`canAddCustomer()`) is an indexed
     * point lookup instead of a `COUNT(*)` over a potentially
     * multi-million-row `customers` table.
     */
    public function up(): void
    {
        Schema::create('subscription_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('metric'); // 'active_customers', 'active_point_rules'
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamp('recalculated_at')->nullable(); // last time this was verified against a true COUNT(*), for drift detection
            $table->timestamps();

            $table->unique(['shop_id', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_usage');
    }
};
