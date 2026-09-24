<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CUSTOMERS: a loyalty program member, linked to a Shopify customer
     * within a shop. Deliberately holds ONLY identity/enrollment/VIP
     * fields — point balances live in the separate `points` table (see
     * that migration's comment for why), keeping this table narrow and
     * fast to scan for the identity/segment queries it's built for
     * (search by email, list by status, VIP tier membership) at the
     * scale of hundreds of thousands to millions of rows per large shop.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_customer_id');
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->foreignId('vip_tier_id')->nullable()->constrained('vip_tiers')->nullOnDelete();
            $table->timestamp('vip_tier_evaluated_at')->nullable();

            $table->string('referral_code')->nullable()->unique();
            $table->foreignId('referred_by_customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();

            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // GDPR erasure zeroes PII columns explicitly (see customers/redact webhook) rather than relying on this alone

            // A given Shopify customer can only be enrolled once per shop.
            $table->unique(['shop_id', 'shopify_customer_id']);
            // Merchant-facing customer list/search: filter by status within a shop.
            $table->index(['shop_id', 'status']);
            // Support/staff lookup by email within a shop (not globally unique — email isn't guaranteed unique even within one Shopify store).
            $table->index(['shop_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
