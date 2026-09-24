<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BILLING: append-only log of every subscription state transition
     * (pending -> active, active -> cancelled, Starter -> Professional,
     * ...). Distinct from `subscriptions` (which holds only the
     * *current* row per the app's "insert a new row per plan change"
     * convention — see that migration's comment) and from the generic
     * `audit_logs` table (which covers security/merchant-action
     * auditing app-wide, not specifically billing state machines).
     *
     * This is what answers "when did this shop upgrade to Professional"
     * or "how many shops downgraded last month" without reconstructing
     * it from `subscriptions.updated_at` timestamps, and is written by
     * `App\Services\Billing\SubscriptionService` on every transition —
     * both merchant-initiated (subscribe/change plan) and
     * Shopify-initiated (webhook-driven status changes, e.g. `frozen`
     * for a declined charge).
     *
     * No `billing_transactions` table exists in this schema — see
     * docs/BILLING.md for why: Shopify Managed Pricing is the system of
     * record for actual charges/invoices; this app never processes a
     * payment itself, so a local financial ledger would just be an
     * unreliable second copy of data Shopify already owns
     * authoritatively. `subscription_events` covers what this app
     * legitimately needs to track (its own entitlement state machine).
     */
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('from_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('to_plan_id')->nullable()->constrained('plans')->nullOnDelete();

            $table->enum('trigger', ['merchant', 'shopify_webhook', 'system'])->default('system');
            $table->json('shopify_payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['shop_id', 'occurred_at']);
            $table->index('to_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
