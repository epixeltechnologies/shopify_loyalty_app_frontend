<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * USERS: shop-staff dashboard accounts — distinct from `customers`
     * (Shopify storefront shoppers enrolled in the loyalty program) and
     * from Shopify's own store-staff accounts. A `shop_id` of null is
     * reserved for internal Anthropic^H^H^H platform-operator accounts
     * (support/admin tooling), which is why it's nullable rather than
     * required — everything else about a user assumes it belongs to
     * exactly one shop; there is no multi-shop user in this design.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Email only needs to be unique WITHIN a shop — the same
            // person could conceivably staff two different stores using
            // one email, and platform-operator accounts (shop_id null)
            // live in their own uniqueness space.
            $table->unique(['shop_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
