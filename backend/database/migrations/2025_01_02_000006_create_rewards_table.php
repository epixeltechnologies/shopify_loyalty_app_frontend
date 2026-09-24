<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REWARDS: the redeemable catalog — what points can be spent on
     * (discount, free shipping, gift). Distinct from `point_rules`
     * (how points are earned).
     */
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage_discount', 'fixed_discount', 'free_shipping', 'gift']);
            $table->unsignedInteger('points_cost');
            $table->json('value'); // e.g. { "percentage": 10 } or { "amount_cents": 500 }
            $table->unsignedInteger('stock_limit')->nullable(); // null = unlimited
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
