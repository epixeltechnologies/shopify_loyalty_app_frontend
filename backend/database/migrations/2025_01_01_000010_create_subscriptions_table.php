<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BILLING: a shop's current and historical subscription state,
     * synced from Shopify Managed Pricing via the
     * `app_subscriptions/update` webhook. This app has NO FREE PLAN — a
     * shop with no row here in an ACTIVE_STATUSES status is blocked by
     * RequireActiveSubscription middleware at the application layer.
     * History is kept (never overwritten in place) by inserting a new
     * row on plan change rather than updating `plan_id` on an existing
     * row, so "what plan was this shop on last March" is answerable.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();

            $table->string('shopify_subscription_id')->nullable()->unique();
            $table->enum('status', [
                'pending', 'active', 'trialing', 'past_due',
                'cancelled', 'expired', 'declined', 'frozen',
            ])->default('pending');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->json('shopify_payload')->nullable(); // raw last-known Shopify payload, for support/audit
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
