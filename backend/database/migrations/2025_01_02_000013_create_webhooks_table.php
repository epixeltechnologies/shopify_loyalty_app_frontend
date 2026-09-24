<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SYSTEM: inbound Shopify webhook log. `shopify_webhook_id` is
     * UNIQUE (nullable, so historical/manually-inserted rows without one
     * don't collide) — Shopify can and does redeliver the same webhook
     * more than once (at-least-once delivery), and this constraint is
     * what makes `WebhookController::handle()` idempotent: a duplicate
     * delivery fails the insert (or is checked for first) instead of
     * double-processing, e.g. double-awarding points for one order.
     */
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();

            $table->string('topic');
            $table->string('shopify_webhook_id')->nullable()->unique();
            $table->json('payload');
            $table->enum('status', ['received', 'processing', 'processed', 'failed'])->default('received');
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['topic', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
