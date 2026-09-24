<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * USERS: fine-grained capability catalog (e.g. 'points.adjust',
     * 'rewards.manage', 'settings.manage', 'billing.manage'). Deliberately
     * NOT plan features (that's `features`/`plan_features` — what the
     * shop's subscription unlocks) and NOT Shopify OAuth scopes (what
     * the app can do to the store) — this is the third, separate axis:
     * what a given staff member is allowed to do within the app.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // 'points.adjust', 'rewards.manage', ...
            $table->string('group')->nullable(); // 'points', 'rewards', 'settings', 'billing'
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
