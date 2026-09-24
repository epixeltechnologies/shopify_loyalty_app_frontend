<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TENANT SYSTEM: `shops` is the tenant root — every Shopify store is
     * one row here, and every tenant-owned table below carries a
     * `shop_id` foreign key filtered by the TenantScope global scope
     * (see App\Models\Concerns\BelongsToShop / docs/MULTI_TENANCY.md).
     * `shopify_domain` is the natural key merchants/support will search
     * by; `id` (surrogate) is what every other table actually references,
     * since a surrogate integer key is cheaper to index/join on at scale
     * than a variable-length string across every child table.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // stable external identifier, safe to expose in URLs/logs instead of the auto-increment id

            $table->string('shopify_domain')->unique();
            $table->string('shopify_id')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('timezone')->nullable();
            $table->string('plan_display_name')->nullable(); // Shopify's own store plan (not this app's subscription plan)

            $table->text('access_token')->nullable(); // encrypted cast at the model layer
            $table->string('scopes')->nullable();

            $table->boolean('is_installed')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->boolean('onboarding_completed')->default(false);

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // preserve billing/audit history after an uninstall rather than losing the row

            $table->index('is_installed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
