<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REFERRALS: the state machine (pending -> completed -> rewarded).
     * The actual reward *granted* for a completed referral is recorded
     * separately in `referral_rewards`, since a single referral program
     * can plausibly reward both the referrer and the referee with
     * different amounts/types — this table tracks the relationship and
     * its status, not the payout.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('referral_code');
            $table->enum('status', ['pending', 'completed', 'rewarded', 'expired'])->default('pending');
            $table->string('qualifying_order_id')->nullable(); // Shopify order GID that completed the referral
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'status']);
            $table->index('referral_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
