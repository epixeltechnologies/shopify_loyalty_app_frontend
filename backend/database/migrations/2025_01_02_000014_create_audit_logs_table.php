<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SYSTEM: append-only audit trail for security-sensitive and
     * merchant-facing actions (reward creation, manual points
     * adjustments, settings changes, subscription changes). Written by
     * App\Services\Audit\AuditLogger, never updated/deleted in normal
     * operation. Deliberately does NOT use BelongsToShop / the
     * tenant-scoped query path — audit logs must remain writable even
     * for shop-less system actions (e.g. a webhook that failed before a
     * shop could be resolved).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('actor_type'); // 'user', 'system', 'shopify_webhook'
            $table->string('action'); // 'reward.created', 'points.manual_adjustment', 'settings.updated', ...
            $table->nullableMorphs('auditable');

            $table->json('changes')->nullable(); // ['before' => [...], 'after' => [...]]
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
