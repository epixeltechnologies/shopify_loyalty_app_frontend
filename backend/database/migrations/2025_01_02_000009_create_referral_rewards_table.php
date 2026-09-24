<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REFERRALS: the actual payout(s) for a completed referral. One
     * `referrals` row can produce up to two `referral_rewards` rows
     * (`beneficiary = 'referrer'` and `beneficiary = 'referee'`), since
     * double-sided referral programs ("give $10, get $10") are the
     * common case and each side's reward can differ in type/amount.
     * `point_transaction_id` links back to the ledger entry that
     * actually paid out the points, so this table is a semantic label
     * over the ledger rather than a second source of truth for points.
     */
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); // the beneficiary customer
            $table->enum('beneficiary', ['referrer', 'referee']);

            $table->enum('reward_type', ['points', 'discount_code']);
            $table->unsignedInteger('points_awarded')->nullable();
            $table->foreignId('point_transaction_id')->nullable()->constrained('point_transactions')->nullOnDelete();
            $table->string('shopify_discount_code')->nullable();

            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // a referral reward can be voided (fraud/chargeback) without deleting the historical record

            $table->index(['shop_id', 'referral_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
