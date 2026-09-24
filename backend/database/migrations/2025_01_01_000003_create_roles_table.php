<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * USERS: roles are global templates (Owner, Manager, Viewer), not
     * per-shop rows — every shop's staff picks from the same fixed set,
     * which keeps authorization logic simple (a policy can check
     * `$user->role->slug === 'owner'` without also scoping by shop).
     * If a shop-custom role system is ever needed, add a nullable
     * `shop_id` column rather than redesigning this table.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // 'owner', 'manager', 'viewer'
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
